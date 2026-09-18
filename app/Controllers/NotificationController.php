<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\FcmToken;
use App\Models\Notification;
use App\Services\AuditLogger;
use App\Services\Notifications\FirebaseNotificationService;
use App\Validators\Validator;

final class NotificationController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('notifications.view');
        $this->view('notifications/index', [
            'history' => Notification::history(),
            'topics' => Notification::TOPICS,
            'success' => Session::flash('success'),
            'error' => Session::flash('error'),
        ]);
    }

    public function send(): void
    {
        $this->requirePermission('notifications.send');
        $input = Request::all();

        $v = new Validator($input);
        $v->required('title', 'Title')->maxLength('title', 150, 'Title')
          ->required('message', 'Message')->maxLength('message', 1000, 'Message')
          ->required('target', 'Target audience')->in('target', Notification::TOPICS, 'Target audience');

        if ($v->fails()) {
            $this->flashAndRedirect('error', implode(' ', $v->errors()), '/notifications');
        }

        $notificationId = Notification::create([
            'title' => trim($input['title']),
            'message' => trim($input['message']),
            'target_type' => 'topic',
            'target_value' => $input['target'],
            'sent_by' => Auth::id(),
        ]);

        $push = new FirebaseNotificationService();
        $result = $push->sendToTopic($input['target'], trim($input['title']), trim($input['message']));

        Notification::markResult($notificationId, $result['ok'] ? 'sent' : 'failed', $result['error'], $result['recipientCount']);
        AuditLogger::log('notification.send', 'notification', $notificationId, null, ['target' => $input['target'], 'result' => $result]);

        $message = $result['ok']
            ? 'Notification sent to topic "' . $input['target'] . '".'
            : 'Notification recorded, but delivery failed: ' . $result['error'];

        $this->flashAndRedirect($result['ok'] ? 'success' : 'error', $message, '/notifications');
    }

    /**
     * Called by the frontend after the Firebase client SDK obtains a device
     * token. Besides storing the token, this also subscribes it to the
     * topics relevant to the user's role server-side — the web SDK has no
     * client-side topic-subscribe call, so without this step a device would
     * never actually receive a topic broadcast even though it's registered.
     */
    public function registerToken(): void
    {
        $token = Request::trimmed('token');
        $deviceType = in_array(Request::input('device_type'), ['web', 'android', 'ios'], true) ? Request::input('device_type') : 'web';

        if ($token === '' || !Auth::check()) {
            $this->json(['success' => false, 'message' => 'Not authenticated or missing token.'], 401);
        }

        FcmToken::register(Auth::id(), $token, $deviceType);

        $topics = ['all-users', 'announcements', 'deed-updates'];
        if (Auth::role() === 'staff') {
            $topics[] = 'staff';
        }

        $push = new FirebaseNotificationService();
        $subscribeErrors = [];
        foreach ($topics as $topic) {
            $result = $push->subscribeToTopic($token, $topic);
            if (!$result['ok']) {
                $subscribeErrors[$topic] = $result['error'];
            }
        }

        if ($subscribeErrors !== []) {
            AuditLogger::log('fcm.topic_subscribe_failed', 'user', Auth::id(), null, $subscribeErrors);
        }

        $this->json(['success' => true, 'message' => 'Device registered for notifications.']);
    }

    public function removeToken(): void
    {
        $token = Request::trimmed('token');
        if ($token === '' || !Auth::check()) {
            $this->json(['success' => false, 'message' => 'Not authenticated or missing token.'], 401);
        }
        FcmToken::remove($token);
        $this->json(['success' => true, 'message' => 'Device unregistered.']);
    }
}

<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Buyer;
use App\Models\Deed;
use App\Models\Notification;
use App\Models\RegistrationDetail;
use App\Models\Seller;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\DeedStatusService;
use App\Services\SmsTemplate;
use App\Services\Notifications\FirebaseNotificationService;
use App\Services\Notifications\TextLkSmsNotificationService;
use App\Validators\Validator;

final class DeedController extends Controller
{
    private const RESEND_COOLDOWN_SECONDS = 60;

    public function index(): void
    {
        $this->requirePermission('deeds.view');

        $filters = [
            'q' => Request::trimmed('q'),
            'category' => Request::trimmed('category'),
            'status' => Request::trimmed('status'),
            'from' => Request::trimmed('from'),
            'to' => Request::trimmed('to'),
            'sort' => Request::trimmed('sort'),
            'dir' => Request::trimmed('dir'),
        ];
        $page = max(1, (int) Request::input('page', 1));

        $result = Deed::search($filters, $page);

        $this->view('deeds/index', [
            'result' => $result,
            'filters' => $filters,
            'categories' => Deed::CATEGORIES,
            'statuses' => Deed::STATUSES,
        ]);
    }

    /** Live-search-as-you-type source for the registry's search box (JSON). */
    public function suggest(): void
    {
        $this->requirePermission('deeds.view');
        $q = Request::trimmed('q');
        $rows = mb_strlen($q) >= 1 ? Deed::suggest($q) : [];
        Response::json(['results' => $rows]);
    }

    public function showCreateForm(): void
    {
        $this->requirePermission('deeds.create');
        $rawErrors = Session::flash('form_errors');
        $this->view('deeds/create', [
            'categories' => Deed::CATEGORIES,
            'errors' => $rawErrors ? json_decode($rawErrors, true) : [],
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('deeds.create');
        $input = Request::all();

        $v = new Validator($input);
        $v->required('buyer_name', 'Buyer full name')->maxLength('buyer_name', 150, 'Buyer full name')
          ->nic('buyer_nic', 'Buyer NIC / ID no.')
          ->mobile('buyer_phone', 'Buyer mobile no.')
          ->required('seller_name', 'Seller full name')->maxLength('seller_name', 150, 'Seller full name')
          ->nic('seller_nic', 'Seller NIC / ID no.')
          ->mobile('seller_phone', 'Seller mobile no.')
          ->required('deed_no', 'Deed no.')->maxLength('deed_no', 60, 'Deed no.')->deedNumber('deed_no', 'Deed no.')
          ->required('deed_category', 'Deed category')->in('deed_category', Deed::CATEGORIES, 'Deed category')
          ->maxLength('folio_number', 60, 'Folio no.')
          ->decimal('amount', 'Amount')
          ->decimal('value', 'Value');

        if ($v->fails()) {
            Session::flash('form_errors', json_encode($v->errors()));
            $this->redirect('/deeds/create');
        }

        if (Deed::findByNumber(trim($input['deed_no']))) {
            Session::flash('form_errors', json_encode(['deed_no' => 'A deed with this number already exists.']));
            $this->redirect('/deeds/create');
        }

        $buyerId = Buyer::findOrCreate(trim($input['buyer_name']), trim($input['buyer_nic'] ?? ''), trim($input['buyer_phone'] ?? ''));
        $sellerId = Seller::findOrCreate(trim($input['seller_name']), trim($input['seller_nic'] ?? ''), trim($input['seller_phone'] ?? ''));

        $deedId = Deed::create([
            'deed_number' => trim($input['deed_no']),
            'category' => $input['deed_category'],
            'buyer_id' => $buyerId,
            'seller_id' => $sellerId,
            'folio_number' => trim($input['folio_number'] ?? ''),
            'amount' => trim($input['amount'] ?? ''),
            'value' => trim($input['value'] ?? ''),
            'other_document_numbers' => trim($input['other_document_numbers'] ?? ''),
        ], Auth::id());

        AuditLogger::log('deed.create', 'deed', $deedId, null, ['deed_number' => $input['deed_no']]);

        Session::flash('success', 'Deed entered.');
        $this->redirect('/deeds/' . $deedId);
    }

    public function show(array $params): void
    {
        $this->requirePermission('deeds.view');
        $deed = Deed::find((int) $params['id']);
        if (!$deed) {
            Response::notFound();
        }

        $this->view('deeds/show', [
            'deed' => $deed,
            'registration' => RegistrationDetail::find($deed['id']),
            'categories' => Deed::CATEGORIES,
            'success' => Session::flash('success'),
            'error' => Session::flash('error'),
        ]);
    }

    public function updateDetails(array $params): void
    {
        $this->requirePermission('deeds.update');
        $id = (int) $params['id'];
        $deed = Deed::find($id);
        if (!$deed) {
            Response::notFound();
        }
        $input = Request::all();

        $v = new Validator($input);
        $v->required('deed_no', 'Deed no.')->maxLength('deed_no', 60, 'Deed no.')->deedNumber('deed_no', 'Deed no.')
          ->required('deed_category', 'Deed category')->in('deed_category', Deed::CATEGORIES, 'Deed category')
          ->maxLength('folio_number', 60, 'Folio no.')
          ->mobile('buyer_phone', 'Buyer mobile no.')
          ->mobile('seller_phone', 'Seller mobile no.')
          ->decimal('amount', 'Amount')
          ->decimal('value', 'Value');

        if ($v->fails()) {
            $this->flashAndRedirect('error', implode(' ', $v->errors()), '/deeds/' . $id);
        }

        $clash = Deed::findByNumber(trim($input['deed_no']));
        if ($clash && (int) $clash['id'] !== $id) {
            $this->flashAndRedirect('error', 'Another deed already uses that deed number.', '/deeds/' . $id);
        }

        $changes = [
            'deed_number' => trim($input['deed_no']),
            'category' => $input['deed_category'],
            'folio_number' => trim($input['folio_number'] ?? ''),
            'amount' => trim($input['amount'] ?? ''),
            'value' => trim($input['value'] ?? ''),
            'other_document_numbers' => trim($input['other_document_numbers'] ?? ''),
        ];
        Deed::updateDetails($id, $changes, Auth::id());

        // The confirmation SMS goes to these numbers, so they must be correctable after entry.
        if (array_key_exists('buyer_phone', $input)) {
            $changes['buyer_mobile'] = trim($input['buyer_phone']);
            Buyer::updateMobile((int) $deed['buyer_id'], $changes['buyer_mobile']);
        }
        if (array_key_exists('seller_phone', $input)) {
            $changes['seller_mobile'] = trim($input['seller_phone']);
            Seller::updateMobile((int) $deed['seller_id'], $changes['seller_mobile']);
        }

        AuditLogger::log('deed.update', 'deed', $id, $deed, $changes);

        $this->flashAndRedirect('success', 'Deed details saved.', '/deeds/' . $id);
    }

    public function updateRegistrationFields(array $params): void
    {
        $this->requirePermission('deeds.update');
        $id = (int) $params['id'];
        $deed = Deed::find($id);
        if (!$deed) {
            Response::notFound();
        }
        $input = Request::all();

        $v = new Validator($input);
        $v->date('register_date', 'Register date')->maxLength('day_book_number', 60, 'Day book no.');
        if ($v->fails()) {
            $this->flashAndRedirect('error', implode(' ', $v->errors()), '/deeds/' . $id);
        }

        $before = RegistrationDetail::find($id);
        RegistrationDetail::updateFields($id, [
            'register_date' => trim($input['register_date'] ?? ''),
            'day_book_number' => trim($input['day_book_number'] ?? ''),
            'new_folio_number' => trim($input['new_folio_number'] ?? ''),
            'notes' => trim($input['notes'] ?? ''),
        ], Auth::id());

        AuditLogger::log('deed.registration_update', 'deed', $id, $before, RegistrationDetail::find($id));

        $this->flashAndRedirect('success', 'Registration details saved.', '/deeds/' . $id);
    }

    /** Submitted -> Reviewed: an explicit staff action, not inferred from any data field. */
    public function markReviewed(array $params): void
    {
        $this->requirePermission('deeds.update');
        $id = (int) $params['id'];
        $deed = Deed::find($id);
        if (!$deed) {
            Response::notFound();
        }
        $registration = RegistrationDetail::find($id);
        if (!empty($registration['reviewed'])) {
            $this->flashAndRedirect('error', 'This deed has already been marked reviewed.', '/deeds/' . $id);
        }

        RegistrationDetail::markReviewed($id, Auth::id());
        $status = DeedStatusService::recalculateAndSave($id, RegistrationDetail::find($id));

        AuditLogger::log('deed.marked_reviewed', 'deed', $id, null, ['status' => $status]);
        $this->flashAndRedirect('success', 'Marked reviewed. Status: ' . Deed::statusLabel($status), '/deeds/' . $id);
    }

    /** Reviewed -> Received: an explicit staff action, requires reviewed to have happened first. */
    public function markReceived(array $params): void
    {
        $this->requirePermission('deeds.update');
        $id = (int) $params['id'];
        $deed = Deed::find($id);
        if (!$deed) {
            Response::notFound();
        }
        $registration = RegistrationDetail::find($id);
        if (empty($registration['reviewed'])) {
            $this->flashAndRedirect('error', 'Mark this deed reviewed before marking it received.', '/deeds/' . $id);
        }
        if (!empty($registration['received'])) {
            $this->flashAndRedirect('error', 'This deed has already been marked received.', '/deeds/' . $id);
        }

        RegistrationDetail::markReceived($id, Auth::id());
        $status = DeedStatusService::recalculateAndSave($id, RegistrationDetail::find($id));

        AuditLogger::log('deed.marked_received', 'deed', $id, null, ['status' => $status]);
        $notify = $this->notifyReceived(Deed::find($id));
        $this->flashAndRedirect($notify['smsOk'] ? 'success' : 'error', 'Marked received. Status: ' . Deed::statusLabel($status) . '. ' . $notify['message'], '/deeds/' . $id);
    }

    /**
     * Runs once, automatically, when a deed is marked Received: the buyer and
     * seller each get the office's confirmation SMS (wording is the
     * admin-editable template; only the deed number changes per deed), and
     * staff get an internal push. Each channel is reported honestly.
     *
     * Never throws. A delivery failure is reported in the flash message and
     * audit log but must not undo the status change that already happened.
     */
    private function notifyReceived(?array $deed): array
    {
        if (!$deed) {
            return ['smsOk' => true, 'message' => ''];
        }
        $id = (int) $deed['id'];

        try {
            $message = SmsTemplate::render(Setting::receivedTemplate(), (string) $deed['deed_number']);

            $sms = new TextLkSmsNotificationService();
            $results = [];
            $alreadySentTo = [];
            foreach (['buyer' => $deed['buyer_mobile'] ?? null, 'seller' => $deed['seller_mobile'] ?? null] as $party => $mobile) {
                $number = $mobile ? TextLkSmsNotificationService::toInternationalFormat($mobile) : null;
                if (!$mobile) {
                    $results[$party] = ['ok' => false, 'error' => 'No mobile number on file.'];
                } elseif ($number !== null && isset($alreadySentTo[$number])) {
                    // Same person listed as both parties (or a shared phone): one SMS is enough.
                    $results[$party] = ['ok' => $alreadySentTo[$number]['ok'], 'error' => $alreadySentTo[$number]['error'], 'duplicate' => true];
                } else {
                    $results[$party] = $sms->send($mobile, $message);
                    if ($number !== null) {
                        $alreadySentTo[$number] = $results[$party];
                    }
                }
            }
            AuditLogger::log('deed.sms_attempted', 'deed', $id, null, $results);

            $pushText = "Deed {$deed['deed_number']} has been received and registered.";
            $push = new FirebaseNotificationService();
            $pushResult = $push->sendToTopic('deed-updates', 'Deed received', $pushText, ['deed_id' => (string) $id]);
            $notificationId = Notification::create([
                'title' => 'Deed received',
                'message' => $pushText,
                'target_type' => 'topic',
                'target_value' => 'deed-updates',
                'related_deed_id' => $id,
                'sent_by' => Auth::id(),
            ]);
            Notification::markResult($notificationId, $pushResult['ok'] ? 'sent' : 'failed', $pushResult['error'], $pushResult['recipientCount']);

            RegistrationDetail::markNotificationSent($id);
            AuditLogger::log('deed.notification_sent', 'deed', $id, null, ['push' => $pushResult, 'sms' => $results]);
        } catch (\Throwable $e) {
            Logger::error('Received-notification failed for deed ' . $id . ': ' . $e->getMessage());
            return ['smsOk' => false, 'message' => 'The confirmation SMS could not be sent because of an internal error. Use "Resend SMS" to try again.'];
        }

        $smsOk = !in_array(false, array_column($results, 'ok'), true);

        $summary = [];
        foreach (['buyer' => 'Buyer', 'seller' => 'Seller'] as $key => $label) {
            $r = $results[$key];
            if ($r['ok'] && !empty($r['duplicate'])) {
                $summary[] = "{$label}: same number as the buyer, so one SMS was sent.";
            } elseif ($r['ok']) {
                $summary[] = "{$label} SMS: sent" . (!empty($r['units']) ? " ({$r['units']} SMS units)" : '') . '.';
            } else {
                $summary[] = "{$label} SMS: not delivered ({$r['error']}).";
            }
        }
        $summary[] = 'Internal push: ' . ($pushResult['ok'] ? 'sent.' : 'not delivered (' . $pushResult['error'] . ').');

        return ['smsOk' => $smsOk, 'message' => implode(' ', $summary)];
    }

    /**
     * Manual "Resend notification": a safety net for when the automatic send
     * failed to reach someone (for example the SMS account ran out of
     * credit). Only for deeds already marked Received, and rate-limited so a
     * double-click or a curious user cannot burn SMS credit.
     */
    public function sendNotification(array $params): void
    {
        $this->requirePermission('notifications.send');
        $id = (int) $params['id'];
        $deed = Deed::find($id);
        if (!$deed) {
            Response::notFound();
        }
        $registration = RegistrationDetail::find($id);
        if (empty($registration['received'])) {
            $this->flashAndRedirect('error', 'The confirmation SMS is only sent once a deed has been marked Received.', '/deeds/' . $id);
        }

        $since = RegistrationDetail::secondsSinceNotification($id);
        if ($since !== null && $since < self::RESEND_COOLDOWN_SECONDS) {
            $wait = self::RESEND_COOLDOWN_SECONDS - $since;
            $this->flashAndRedirect('error', "A notification was just sent. Please wait {$wait} seconds before resending.", '/deeds/' . $id);
        }

        AuditLogger::log('deed.notification_resend', 'deed', $id);
        $notify = $this->notifyReceived($deed);
        $this->flashAndRedirect($notify['smsOk'] ? 'success' : 'error', 'Notification resent. ' . $notify['message'], '/deeds/' . $id);
    }

    public function archive(array $params): void
    {
        $this->requirePermission('deeds.archive');
        $id = (int) $params['id'];
        $deed = Deed::find($id);
        if (!$deed) {
            Response::notFound();
        }

        Deed::archive($id);
        AuditLogger::log('deed.archive', 'deed', $id, $deed, null);

        $this->flashAndRedirect('success', 'Deed archived.', '/deeds');
    }
}

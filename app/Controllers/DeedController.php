<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Buyer;
use App\Models\Deed;
use App\Models\Notification;
use App\Models\RegistrationDetail;
use App\Models\Seller;
use App\Services\AuditLogger;
use App\Services\DeedStatusService;
use App\Services\Notifications\FirebaseNotificationService;
use App\Services\Notifications\TextLkSmsNotificationService;
use App\Validators\Validator;

final class DeedController extends Controller
{
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
          ->required('deed_no', 'Deed no.')->maxLength('deed_no', 60, 'Deed no.')
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
        $v->required('deed_no', 'Deed no.')->maxLength('deed_no', 60, 'Deed no.')
          ->required('deed_category', 'Deed category')->in('deed_category', Deed::CATEGORIES, 'Deed category')
          ->maxLength('folio_number', 60, 'Folio no.')
          ->decimal('amount', 'Amount')
          ->decimal('value', 'Value');

        if ($v->fails()) {
            $this->flashAndRedirect('error', implode(' ', $v->errors()), '/deeds/' . $id);
        }

        Deed::updateDetails($id, [
            'deed_number' => trim($input['deed_no']),
            'category' => $input['deed_category'],
            'folio_number' => trim($input['folio_number'] ?? ''),
            'amount' => trim($input['amount'] ?? ''),
            'value' => trim($input['value'] ?? ''),
            'other_document_numbers' => trim($input['other_document_numbers'] ?? ''),
        ], Auth::id());

        AuditLogger::log('deed.update', 'deed', $id, $deed, $input);

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
        $this->flashAndRedirect('success', 'Marked received. Status: ' . Deed::statusLabel($status), '/deeds/' . $id);
    }

    /**
     * Replaces the prototype's fake "Send SMS" button. Available once the
     * deed has been marked Received (its registration progress is done).
     * Attempts real delivery on two independent channels and reports each
     * honestly:
     *   - real SMS to the buyer/seller's mobile numbers via text.lk (see
     *     /firebase/SETUP.md's SMS note and TextLkSmsNotificationService)
     *   - an internal FCM push to the 'deed-updates' topic, for staff/admin
     *     awareness (works once Firebase is configured)
     * This is independent of deeds.status (only Submitted/Reviewed/Received
     * exist) — it's a courtesy action, not a 4th workflow stage.
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
            $this->flashAndRedirect('error', 'Mark this deed received before notifying the buyer and seller.', '/deeds/' . $id);
        }
        if (!empty($registration['notification_sent'])) {
            $this->flashAndRedirect('error', 'Notification already sent for this deed.', '/deeds/' . $id);
        }

        $sms = new TextLkSmsNotificationService();
        $smsResults = [];
        foreach (['buyer' => $deed['buyer_mobile'], 'seller' => $deed['seller_mobile']] as $party => $mobile) {
            $smsResults[$party] = $mobile
                ? $sms->send($mobile, "Nithi Docket: Deed {$deed['deed_number']} has been registered.")
                : ['ok' => false, 'error' => 'No mobile number on file.'];
        }
        AuditLogger::log('deed.sms_attempted', 'deed', $id, null, $smsResults);

        $push = new FirebaseNotificationService();
        $pushResult = $push->sendToTopic(
            'deed-updates',
            'Deed registered',
            "Deed {$deed['deed_number']} has been successfully registered.",
            ['deed_id' => (string) $id]
        );
        $notificationId = Notification::create([
            'title' => 'Deed registered',
            'message' => "Deed {$deed['deed_number']} has been successfully registered.",
            'target_type' => 'topic',
            'target_value' => 'deed-updates',
            'related_deed_id' => $id,
            'sent_by' => Auth::id(),
        ]);
        Notification::markResult($notificationId, $pushResult['ok'] ? 'sent' : 'failed', $pushResult['error'], $pushResult['recipientCount']);

        RegistrationDetail::markNotificationSent($id);
        AuditLogger::log('deed.notification_sent', 'deed', $id, null, ['push' => $pushResult, 'sms' => $smsResults]);

        $smsSummary = [];
        foreach (['buyer' => 'Buyer', 'seller' => 'Seller'] as $key => $label) {
            $smsSummary[] = $smsResults[$key]['ok']
                ? "{$label} SMS: sent."
                : "{$label} SMS: not delivered ({$smsResults[$key]['error']}).";
        }

        $summary = 'Marked as notified. '
            . 'Internal push: ' . ($pushResult['ok'] ? 'sent.' : 'not delivered (' . $pushResult['error'] . ').') . ' '
            . implode(' ', $smsSummary);

        $this->flashAndRedirect('success', $summary, '/deeds/' . $id);
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

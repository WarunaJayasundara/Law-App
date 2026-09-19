<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Env;
use App\Core\Request;
use App\Core\Session;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\SmsTemplate;

final class SettingsController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('settings.manage');

        $draft = Session::flash('template_draft');

        $this->view('settings/index', [
            'template' => $draft ?? Setting::receivedTemplate(),
            'isCustom' => Setting::isReceivedTemplateCustomised(),
            'senderId' => (string) Env::get('TEXTLK_SENDER_ID', ''),
            'smsConfigured' => (bool) Env::get('TEXTLK_API_TOKEN'),
            'success' => Session::flash('success'),
            'error' => Session::flash('error'),
        ]);
    }

    public function update(): void
    {
        $this->requirePermission('settings.manage');

        if (Request::input('reset') === '1') {
            $before = Setting::get(Setting::RECEIVED_TEMPLATE);
            Setting::delete(Setting::RECEIVED_TEMPLATE);
            AuditLogger::log('settings.sms_template_reset', 'setting', null, ['template' => $before], null);
            $this->flashAndRedirect('success', 'The message was reset to the default wording.', '/settings');
        }

        $template = SmsTemplate::normalize((string) Request::input('template', ''));
        $error = SmsTemplate::validate($template);
        if ($error !== null) {
            Session::flash('template_draft', $template);
            $this->flashAndRedirect('error', $error, '/settings');
        }

        $before = Setting::get(Setting::RECEIVED_TEMPLATE);
        Setting::set(Setting::RECEIVED_TEMPLATE, $template, Auth::id());
        AuditLogger::log('settings.sms_template_updated', 'setting', null, ['template' => $before], ['template' => $template]);

        $estimate = SmsTemplate::estimate(SmsTemplate::render($template, '00000'));
        $this->flashAndRedirect(
            'success',
            "Message saved. Each recipient will get {$estimate['parts']} SMS ({$estimate['length']} characters, {$estimate['encoding']}), based on a 5-digit deed number.",
            '/settings'
        );
    }
}

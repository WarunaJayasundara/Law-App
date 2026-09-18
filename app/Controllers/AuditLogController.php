<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\AuditLog;

final class AuditLogController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('audit.view');
        $this->view('audit/index', [
            'logs' => AuditLog::recent(200, [
                'entity_type' => Request::trimmed('entity_type'),
            ]),
            'entityFilter' => Request::trimmed('entity_type'),
        ]);
    }
}

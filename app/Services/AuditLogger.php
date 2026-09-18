<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Request;
use App\Models\AuditLog;

final class AuditLogger
{
    public static function log(string $action, string $entityType, ?int $entityId, ?array $old = null, ?array $new = null): void
    {
        AuditLog::record(
            Auth::id(),
            $action,
            $entityType,
            $entityId,
            $old,
            $new,
            Request::ip(),
            Request::userAgent()
        );
    }
}

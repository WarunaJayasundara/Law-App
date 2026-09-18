<?php

namespace App\Services;

use App\Models\Deed;

/**
 * Recomputes a deed's status from explicit staff actions (not from
 * whether data fields happen to be filled in) and persists it to
 * `deeds.status` so it can be indexed and filtered on.
 *
 * Rule:
 *   received (staff clicked "Mark received")  -> Received
 *   reviewed (staff clicked "Mark reviewed")   -> Reviewed
 *   otherwise                                  -> Submitted
 *
 * A deed can't reach Received without having been marked Reviewed first
 * (enforced in DeedController, not here) — this method only derives the
 * label from whatever flags are already true.
 */
final class DeedStatusService
{
    public static function deriveStatus(array $registrationDetail): string
    {
        if (!empty($registrationDetail['received'])) {
            return 'Received';
        }
        if (!empty($registrationDetail['reviewed'])) {
            return 'Reviewed';
        }
        return 'Submitted';
    }

    public static function recalculateAndSave(int $deedId, array $registrationDetail): string
    {
        $status = self::deriveStatus($registrationDetail);
        Deed::updateStatus($deedId, $status);
        return $status;
    }
}

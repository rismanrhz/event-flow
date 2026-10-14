<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogService
{
    /**
     * Record an auditable action.
     */
    public function record(
        string $action,
        string $module,
        ?string $description = null,
        ?int $userId = null,
        ?int $eventId = null,
        array $meta = [],
        ?Request $request = null
    ): ActivityLog {
        $request ??= request();

        return ActivityLog::create([
            'user_id' => $userId,
            'event_id' => $eventId,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'ip_address' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255),
            'meta' => $meta ?: null,
        ]);
    }
}

<?php

use App\Services\ActivityLogService;

/**
 * Convenience helper for recording audit log entries:
 *     activity()->record('user.login', 'auth', 'User logged in', $user->id);
 */
function activity(): ActivityLogService
{
    return app(ActivityLogService::class);
}

/**
 * Format a numeric value as an Indonesian Rupiah string.
 */
function format_rupiah(int|float|string $amount): string
{
    return 'Rp'.number_format((float) $amount, 0, ',', '.');
}

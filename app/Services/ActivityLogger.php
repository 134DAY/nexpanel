<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Logs all important actions for audit trail
 */
class ActivityLogger
{
    public static function log(string $action, string $details = '', ?string $level = 'info'): void
    {
        try {
            DB::table('activity_logs')->insert([
                'user_id' => Auth::id(),
                'action' => $action,
                'details' => $details,
                'level' => $level,
                'ip' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Don't crash if logging fails — just continue
            \Log::error('ActivityLogger failed: ' . $e->getMessage());
        }
    }

    public static function info(string $action, string $details = ''): void
    {
        self::log($action, $details, 'info');
    }

    public static function warning(string $action, string $details = ''): void
    {
        self::log($action, $details, 'warning');
    }

    public static function danger(string $action, string $details = ''): void
    {
        self::log($action, $details, 'danger');
    }
}

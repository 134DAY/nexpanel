<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanelSetting extends Model
{
    protected $table = 'panel_settings';

    protected $fillable = [
        'user_id',
        'panel_name',
        'timezone',
        'session_timeout',
        'language',
    ];

    /**
     * Get common timezone options
     */
    public static function timezones(): array
    {
        return [
            'Asia/Bangkok' => 'Asia/Bangkok (UTC+7)',
            'Asia/Tokyo' => 'Asia/Tokyo (UTC+9)',
            'Asia/Singapore' => 'Asia/Singapore (UTC+8)',
            'Asia/Shanghai' => 'Asia/Shanghai (UTC+8)',
            'Asia/Kolkata' => 'Asia/Kolkata (UTC+5:30)',
            'Europe/London' => 'Europe/London (UTC+0)',
            'Europe/Berlin' => 'Europe/Berlin (UTC+1)',
            'America/New_York' => 'America/New_York (UTC-5)',
            'America/Chicago' => 'America/Chicago (UTC-6)',
            'America/Los_Angeles' => 'America/Los_Angeles (UTC-8)',
            'Pacific/Auckland' => 'Pacific/Auckland (UTC+12)',
            'Australia/Sydney' => 'Australia/Sydney (UTC+11)',
        ];
    }
}

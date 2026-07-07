<?php

namespace App\Http\Controllers;

use App\Models\NotificationSetting;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** All keys the settings form manages. */
    private const KEYS = [
        'discord_enabled', 'discord_webhook',
        'telegram_enabled', 'telegram_token', 'telegram_chat',
        'webhook_enabled', 'webhook_url',
        'email_enabled', 'email_to',
    ];

    public function index()
    {
        return view('notifications.index', [
            'settings' => NotificationSetting::map(),
        ]);
    }

    public function update(Request $request)
    {
        foreach (self::KEYS as $key) {
            if (str_ends_with($key, '_enabled')) {
                NotificationSetting::put($key, $request->boolean($key) ? '1' : '0');
            } else {
                NotificationSetting::put($key, $request->input($key));
            }
        }
        ActivityLogger::log('notifications.update', 'Updated notification settings');

        return back()->with('success', 'Notification settings saved.');
    }

    public function test(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => 'required|in:discord,telegram,webhook,email',
        ]);

        // Persist current form values first so the test uses what the user typed.
        foreach (self::KEYS as $key) {
            if ($request->has($key) && ! str_ends_with($key, '_enabled')) {
                NotificationSetting::put($key, $request->input($key));
            }
        }

        $result = NotificationService::sendTo(
            $data['channel'],
            'NexPanel Test Notification',
            'If you can read this, your ' . ucfirst($data['channel']) . ' channel is working correctly. 🎉',
            'info'
        );

        return response()->json([
            'ok'      => $result === 'ok',
            'message' => $result === 'ok' ? 'Test message sent successfully.' : $result,
        ]);
    }
}

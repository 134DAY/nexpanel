<?php

namespace App\Services;

use App\Models\NotificationSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends alerts through the LINE Messaging API — the panel's notification
 * channel (scope 1.3.3.1). Every send is best-effort: failures are logged,
 * never thrown, so notifications can never break the request that triggered
 * them.
 */
class NotificationService
{
    public const CHANNELS = ['line'];

    /**
     * Fan a message out to all enabled channels.
     *
     * @return array<string,string> channel => 'ok' | error message
     */
    public static function send(string $title, string $message, string $level = 'info'): array
    {
        $results = [];
        foreach (self::CHANNELS as $channel) {
            if (NotificationSetting::get("{$channel}_enabled") !== '1') {
                continue;
            }
            $results[$channel] = self::sendTo($channel, $title, $message, $level);
        }

        return $results;
    }

    /** Send to a single channel regardless of its enabled flag (used by "Test"). */
    public static function sendTo(string $channel, string $title, string $message, string $level = 'info'): string
    {
        try {
            return match ($channel) {
                'line'  => self::line($title, $message),
                default => 'unknown channel',
            };
        } catch (\Throwable $e) {
            Log::warning("Notification [{$channel}] failed: " . $e->getMessage());

            return $e->getMessage();
        }
    }

    /**
     * LINE Messaging API (push message). Replaces the retired LINE Notify
     * service. Requires a Messaging API channel access token and a recipient
     * id (userId / groupId / roomId that has added the bot as a friend).
     */
    private static function line(string $title, string $message): string
    {
        $token = (string) NotificationSetting::get('line_token');
        $to    = (string) NotificationSetting::get('line_to');
        if ($token === '' || $to === '') {
            return 'LINE channel access token / recipient id is not set';
        }
        $res = Http::timeout(8)
            ->withToken($token)
            ->post('https://api.line.me/v2/bot/message/push', [
                'to'       => $to,
                'messages' => [[
                    'type' => 'text',
                    'text' => "{$title}\n{$message}",
                ]],
            ]);

        return $res->successful() ? 'ok' : "HTTP {$res->status()}: {$res->body()}";
    }
}

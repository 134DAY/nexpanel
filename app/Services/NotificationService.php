<?php

namespace App\Services;

use App\Models\NotificationSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends alerts to the configured channels (Discord / Telegram / LINE / generic
 * webhook / email). Every send is best-effort: failures are logged, never
 * thrown, so notifications can never break the request that triggered them.
 */
class NotificationService
{
    public const CHANNELS = ['discord', 'telegram', 'line', 'webhook', 'email'];

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
                'discord'  => self::discord($title, $message, $level),
                'telegram' => self::telegram($title, $message),
                'line'     => self::line($title, $message),
                'webhook'  => self::webhook($title, $message, $level),
                'email'    => self::email($title, $message),
                default    => 'unknown channel',
            };
        } catch (\Throwable $e) {
            Log::warning("Notification [{$channel}] failed: " . $e->getMessage());

            return $e->getMessage();
        }
    }

    private static function discord(string $title, string $message, string $level): string
    {
        $url = (string) NotificationSetting::get('discord_webhook');
        if ($url === '') {
            return 'Discord webhook URL is not set';
        }
        $color = match ($level) {
            'danger', 'error' => 0xEF4444,
            'warning'         => 0xF59E0B,
            default           => 0x10B981,
        };
        $res = Http::timeout(8)->post($url, [
            'username' => 'NexPanel',
            'embeds'   => [[
                'title'       => $title,
                'description' => $message,
                'color'       => $color,
                'footer'      => ['text' => 'NexPanel • ' . gethostname()],
                'timestamp'   => now()->toIso8601String(),
            ]],
        ]);

        return $res->successful() ? 'ok' : "HTTP {$res->status()}: {$res->body()}";
    }

    private static function telegram(string $title, string $message): string
    {
        $token = (string) NotificationSetting::get('telegram_token');
        $chat  = (string) NotificationSetting::get('telegram_chat');
        if ($token === '' || $chat === '') {
            return 'Telegram bot token / chat id is not set';
        }
        $res = Http::timeout(8)->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id'    => $chat,
            'text'       => "*{$title}*\n{$message}",
            'parse_mode' => 'Markdown',
        ]);

        return $res->successful() ? 'ok' : "HTTP {$res->status()}: {$res->body()}";
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

    private static function webhook(string $title, string $message, string $level): string
    {
        $url = (string) NotificationSetting::get('webhook_url');
        if ($url === '') {
            return 'Webhook URL is not set';
        }
        $res = Http::timeout(8)->post($url, [
            'source'    => 'nexpanel',
            'host'      => gethostname(),
            'title'     => $title,
            'message'   => $message,
            'level'     => $level,
            'timestamp' => now()->toIso8601String(),
        ]);

        return $res->successful() ? 'ok' : "HTTP {$res->status()}: {$res->body()}";
    }

    private static function email(string $title, string $message): string
    {
        $to = (string) NotificationSetting::get('email_to');
        if ($to === '') {
            return 'Recipient email is not set';
        }
        Mail::raw($message, function ($mail) use ($to, $title) {
            $mail->to($to)->subject("[NexPanel] {$title}");
        });

        return 'ok';
    }
}

<?php

namespace App\Services\Alerts;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends a notification's toTelegram() text through the Telegram Bot API, to
 * the chat id in the user's alert settings. Needs TELEGRAM_BOT_TOKEN
 * (config services.telegram.bot_token); without it nothing is sent.
 */
class TelegramChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = AlertPreferences::for($notifiable)->telegramChatId;

        if (! $token || ! $chatId || ! method_exists($notification, 'toTelegram')) {
            return;
        }

        $response = Http::asJson()->timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $notification->toTelegram($notifiable),
            'disable_web_page_preview' => true,
        ]);

        if ($response->failed()) {
            // A blocked bot or wrong chat id shouldn't stop the other channels.
            Log::warning('Telegram alert failed', ['status' => $response->status(), 'user' => $notifiable->getKey()]);
        }
    }
}

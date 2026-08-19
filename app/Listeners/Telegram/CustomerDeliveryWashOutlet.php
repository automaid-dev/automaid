<?php

namespace App\Listeners\Telegram;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Notifications\SendTelegramNotification;

class CustomerDeliveryWashOutlet
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        if (app()->environment(['production'])) {
            $chat_id = config('services.telegram-bot-api.groups.prod');
        }
        else {
            $chat_id = config('services.telegram-bot-api.groups.dev');
        }

        // Guard against an unconfigured chat_id (e.g. TELEGRAM_GROUP_PROD
        // was never set in .env, so this defaults to an empty string).
        // Previously this was only caught by the try/catch below AFTER
        // attempting the send, which still meant a real HTTP call to
        // Telegram's API that was guaranteed to fail with a 400 "chat_id
        // is empty" error every single time. Skipping the call entirely
        // when there's nothing to send to avoids that wasted request and
        // makes the real problem (missing .env config) obvious in the
        // log instead of looking like a Telegram/Guzzle failure.
        if (empty($chat_id)) {
            \Log::warning('Telegram chat_id not configured — skipping Telegram notification.', ['listener' => static::class]);
            return;
        }
        $message = "Delivery to wash outlet: 🛵 Your laundry is en route to the wash outlet. Soon it’ll be squeaky clean! 🧼";
        try {
            $event->user->notify(new SendTelegramNotification($chat_id, $message));
        } catch (\Exception $e) {
            \Log::error('listener', ['context' => $e]);
        }
    }
}

<?php

namespace App\Listeners\Telegram;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Notifications\SendTelegramNotification;

class CustomerDeliveryToCustomer
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
        $message = "Your laundry is on the way: 🚴‍♂️ Your clean clothes are on the move! Get ready for that fresh laundry smell at your door!";
        try {
            $event->user->notify(new SendTelegramNotification($chat_id, $message));
        } catch (\Exception $e) {
            \Log::error('listener', ['context' => $e]);
        }
    }
}

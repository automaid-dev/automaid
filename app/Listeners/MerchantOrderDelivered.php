<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Notifications\MerchantOrderDelivered as MerchantOrderDelivered2;

class MerchantOrderDelivered
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
        try {
            $event->user->notify(new MerchantOrderDelivered2($event->user, $event->job));
        } catch (\Exception $e) {
            \Log::error('listener', ['context' => $e]);
        }
    }
}

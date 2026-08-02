<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Notifications\MerchantAdminCancelOrder as MerchantAdminCancelOrder2;

class MerchantAdminCancelOrder
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
            $event->user->notify(new MerchantAdminCancelOrder2($event->user, $event->order));
        } catch (\Exception $e) {
            \Log::error('listener', ['context' => $e]);
        }
    }
}

<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Notifications\RiderAdminCancelOrder as RiderAdminCancelOrder2;

class RiderAdminCancelOrder
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
            $event->user->notify(new RiderAdminCancelOrder2($event->user, $event->order));
        } catch (\Exception $e) {
            \Log::error('listener', ['context' => $e]);
        }
    }
}

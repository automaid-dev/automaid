<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Notifications\CustomerDeliveryToCustomer as CustomerDeliveryToCustomer2;

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
        try {
            $event->user->notify(new CustomerDeliveryToCustomer2($event->user, $event->job));
        } catch (\Exception $e) {
            \Log::error('listener', ['context' => $e]);
        }
    }
}

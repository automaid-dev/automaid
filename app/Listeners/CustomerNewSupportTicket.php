<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Notifications\CustomerNewSupportTicket as CustomerNewSupportTicket2;

class CustomerNewSupportTicket
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
            $event->user->notify(new CustomerNewSupportTicket2($event->user, $event->ticket, $event->type));
        } catch (\Exception $e) {
            \Log::error('listener', ['context' => $e]);
        }
    }
}

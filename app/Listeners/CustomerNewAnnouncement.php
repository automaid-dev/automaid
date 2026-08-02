<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Notifications\CustomerNewAnnouncement as CustomerNewAnnouncement2;

class CustomerNewAnnouncement
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * [handle description]
     * @param  object $event [description]
     * @return [type]        [description]
     */
    public function handle(object $event): void
    {
        foreach ($event->users as $user) {
            try {
                $user->notify(new CustomerNewAnnouncement2($user, $event->announcement));
            } catch (\Exception $e) {
                \Log::error('CustomerNewAnnouncement failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

}

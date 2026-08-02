<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerNewSupportTicket
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $ticket;
    public $type;

    /**
     * Create a new event instance.
     */
    public function __construct($user, $ticket, $type)
    {
        $this->user = $user;        
        $this->ticket = $ticket;
        $this->type = $type;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}

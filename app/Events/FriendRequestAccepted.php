<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FriendRequestAccepted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $accepterId;
    public $accepterName;
    public $requesterId;

    /**
     * Create a new event instance.
     */
    public function __construct($accepterId, $accepterName, $requesterId)
    {
        $this->accepterId = $accepterId;
        $this->accepterName = $accepterName;
        $this->requesterId = $requesterId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->requesterId),
        ];
    }

    public function broadcastAs()
    {
        return 'friend-accepted';
    }
}

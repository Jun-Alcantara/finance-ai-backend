<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AiTyping implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $sessionId;

    /**
     * Create a new event instance.
     */
    public function __construct($sessionId)
    {
        $this->sessionId = $sessionId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $userId = str_replace('user-', '', $this->sessionId);

        return [
            new PrivateChannel('chat.user.' . $userId),
        ];
    }

    public function broadcastAs()
    {
        return 'typing';
    }
    
    public function broadcastWith()
    {
        return [
            'sessionId' => $this->sessionId,
            'source' => 'ai'
        ];
    }
}

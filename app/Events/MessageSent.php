<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(\App\Models\Message $message)
    {
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Extract user ID from session_id (e.g. "user-1" -> "1") or rely on session_id being "user-1"
        // Let's assume session_id IS "user-{id}"
        // But for safety, let's extract the suffix or just match the string if channels.php expected "chat.user.{id}"
        // Actually channels.php route is 'chat.user.{id}'.
        // So we should broadcast to 'chat.user.' . $userId.
        // We will store session_id as 'user-{id}'.
        
        $userId = str_replace('user-', '', $this->message->session_id);

        return [
            new PrivateChannel('chat.user.' . $userId),
        ];
    }

    public function broadcastAs()
    {
        return 'MessageSent';
    }
}

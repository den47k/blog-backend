<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationDeleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $conversationId;
    public $userIds;

    public function __construct($conversationId, array $userIds)
    {
        $this->conversationId = $conversationId;
        $this->userIds = $userIds;
    }

    public function broadcastAs()
    {
        return 'ConversationDeleted';
    }

    public function broadcastWith()
    {
        return ['id' => $this->conversationId];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}

<?php

namespace App\Events;

use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $conversation;
    public $userId;

    public function __construct(Conversation $conversation, User $user)
    {
        $this->conversation = $conversation;
        $this->userId = $user->id;
    }

    public function broadcastAs()
    {
        return 'ConversationCreated';
    }

    public function broadcastWith()
    {
        return (new ConversationResource($this->conversation))->resolve();
    }

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->userId);
    }
}

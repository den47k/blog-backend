<?php

namespace App\Events;

use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationCreatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Conversation $conversation;
    public User $recipient;

    public function __construct(Conversation $conversation, User $recipient)
    {
        $this->conversation = $conversation;
        $this->recipient = $recipient;
    }

    public function broadcastAs()
    {
        return 'ConversationCreated';
    }

    public function broadcastWith()
    {
        return [
            'conversation' => ConversationResource::forUser($this->conversation, $this->recipient)->resolve()
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->recipient->id)
        ];
    }
}

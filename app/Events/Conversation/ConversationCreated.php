<?php

namespace App\Events\Conversation;

use App\Http\Resources\Conversation\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationCreated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public Conversation $conversation;

    public User $recipient;

    public bool $hasUnread;

    public function __construct(Conversation $conversation, User $recipient, bool $hasUnread)
    {
        $this->conversation = $conversation;
        $this->recipient = $recipient;
        $this->hasUnread = $hasUnread;
    }

    public function broadcastAs()
    {
        return 'conversation.created';
    }

    public function broadcastWith()
    {
        return [
            'conversation' => (new ConversationResource($this->conversation, $this->recipient, hasUnread: $this->hasUnread))->resolve(),
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->recipient->id),
        ];
    }
}

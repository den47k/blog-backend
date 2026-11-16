<?php

namespace App\Events\Message;

use App\Http\Resources\Message\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeletedEvent implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Conversation $conversation;

    public string $deletedId;

    public bool $wasLastMessage;

    public ?array $newLastMessage;

    public User $recipient;

    public bool $hasUnread;

    public function __construct(
        Conversation $conversation,
        string $deletedId,
        bool $wasLastMessage,
        ?Message $newLastMessage,
        User $recipient,
        bool $hasUnread,
    ) {
        $this->conversation = $conversation;
        $this->deletedId = $deletedId;
        $this->wasLastMessage = $wasLastMessage;
        $this->newLastMessage = $newLastMessage ? (new MessageResource($newLastMessage))->resolve() : null;
        $this->recipient = $recipient;
        $this->hasUnread = $hasUnread;
    }

    public function broadcastAs(): string
    {
        return 'MessageDeletedEvent';
    }

    public function broadcastWith(): array
    {
        return [
            'conversationId' => $this->conversation->id,
            'deletedId' => $this->deletedId,
            'wasLastMessage' => $this->wasLastMessage,
            'newLastMessage' => $this->newLastMessage,
            'hasUnread' => $this->hasUnread,
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->conversation->id),
            new PrivateChannel('user.'.$this->recipient->id),
        ];
    }
}

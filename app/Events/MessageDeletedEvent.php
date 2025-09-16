<?php

namespace App\Events;

use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ConversationService;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeletedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Conversation $conversation;
    public string $deletedId;
    public bool $wasLastMessage;
    public ?array $newLastMessage;
    public array $recipients;

    public function __construct(
        Conversation $conversation,
        string $deletedId,
        bool $wasLastMessage,
        ?Message $newLastMessage,
        array $recipients = []
    ) {
        $this->conversation = $conversation;
        $this->deletedId = $deletedId;
        $this->wasLastMessage = $wasLastMessage;
        $this->newLastMessage = $newLastMessage ? (new MessageResource($newLastMessage))->resolve() : null;
        $this->recipients = $recipients;
    }

    public function broadcastAs(): string
    {
        return 'MessageDeletedEvent';
    }

    public function broadcastWith(): array
    {
        $conversationService = app(ConversationService::class);
        $recipient = $this->recipients[0] ?? null; // unsafe, refactor for multiple recipinets in group conversations

        return [
            'conversationId' => $this->conversation->id,
            'deletedId' => $this->deletedId,
            'wasLastMessage' => $this->wasLastMessage,
            'newLastMessage' => $this->newLastMessage,
            'hasUnread' => $recipient
                ? $conversationService->hasUnreadMessages($this->conversation, $recipient)
                : false,
        ];
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('conversation.' . $this->conversation->id)
        ];

        foreach ($this->recipients as $recipient) {
            $channels[] = new PrivateChannel('user.' . $recipient->id);
        }

        return $channels;
    }
}

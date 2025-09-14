<?php

namespace App\Events;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeletedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $conversationId;
    public string $deletedId;
    public bool $wasLastMessage;
    public ?array $newLastMessage;
    public array $recipients;

    public function __construct(
        string $conversationId,
        string $deletedId,
        bool $wasLastMessage,
        ?Message $newLastMessage,
        array $recipients = []
    ) {
        $this->conversationId = $conversationId;
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
        return [
            'conversationId' => $this->conversationId,
            'deletedId' => $this->deletedId,
            'wasLastMessage' => $this->wasLastMessage,
            'newLastMessage' => $this->newLastMessage
        ];
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('conversation.' . $this->conversationId)
        ];

        foreach ($this->recipients as $recipient) {
            $channels[] = new PrivateChannel('user.' . $recipient->id);
        }

        return $channels;
    }
}

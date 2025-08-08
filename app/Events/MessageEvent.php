<?php

namespace App\Events;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $operationType;
    public Message $message;
    public array $recipients;
    public ?bool $wasLastMessage;
    public ?Message $newLastMessage;

    public function __construct(
        string $operationType,
        Message $message,
        array $recipients = [],
        ?bool $wasLastMessage = null,
        ?Message $newLastMessage = null
    ) {
        $this->operationType = $operationType;
        $this->message = $message;
        $this->recipients = $recipients;
        $this->wasLastMessage = $wasLastMessage;
        $this->newLastMessage = $newLastMessage;
    }

    public function broadcastAs(): string
    {
        return 'MessageEvent';
    }

    public function broadcastWith(): array
    {
        $payload = [
            'operation' => $this->operationType,
        ];

        if ($this->operationType === 'delete') {
            $payload = array_merge($payload, [
                'conversationId' => $this->message->conversation_id,
                'deletedId' => $this->message->id,
                'wasLastMessage' => $this->wasLastMessage ?? false,
                'newLastMessage' => $this->newLastMessage
                    ? (new MessageResource($this->newLastMessage))->resolve()
                    : null
            ]);
        } else {
            $payload['message'] = (new MessageResource($this->message))->resolve();
        }

        return $payload;
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('conversation.' . $this->message->conversation_id)
        ];

        foreach ($this->recipients as $recipient) {
            $channels[] = new PrivateChannel('user.' . $recipient->id);
        }

        return $channels;
    }
}

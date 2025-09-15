<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationDeletedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $conversationId;
    public array $recipients;

    public function __construct(string $conversationId, array $recipients = [])
    {
        $this->conversationId = $conversationId;
        $this->recipients = $recipients;
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
        $channels = [];

        foreach ($this->recipients as $recipient) {
            $channels[] = new PrivateChannel('user.' . $recipient->id);
        }

        return $channels;
    }
}

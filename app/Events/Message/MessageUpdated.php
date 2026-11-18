<?php

namespace App\Events\Message;

use App\Http\Resources\Message\MessageResource;
use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public Message $message;

    public array $participants;

    public function __construct(Message $message, array $participants = [])
    {
        $this->message = $message;
        $this->participants = $participants;
    }

    public function broadcastAs(): string
    {
        return 'message.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => (new MessageResource($this->message))->resolve(),
        ];
    }

    public function broadcastOn(): array
    {
        return array_map(
            fn (User $user) => new PrivateChannel("user.{$user->id}"),
            $this->participants,
        );
    }
}

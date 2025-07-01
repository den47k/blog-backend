<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MessageService
{
    public function getMessagesForConversation(Conversation $conversation, int $perPage = 30)
    {
        return $conversation->messages()
            ->with('user:id,name,tag')
            ->latest()
            ->paginate($perPage);
    }

    public function storeMessage(Conversation $conversation, User $user, array $data): Message
    {
        return DB::transaction(function () use ($conversation, $user, $data) {
            $message = $conversation->messages()->create([
                'user_id' => $user->id,
                'content' => $data['content']
            ]);

            $conversation->update([
                'last_message_id' => $message->id
            ]);

            $conversation->participants()
                ->whereNull('joined_at')
                ->update(['joined_at' => now()]);

            // event(new MessageSent($message)); ToDO

            return $message;
        });
    }
}

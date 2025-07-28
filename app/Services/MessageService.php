<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class MessageService
{
    public function getMessagesForConversation(Conversation $conversation, int $perPage = 30)
    {
        return $conversation->messages()
            ->with('user:id,name,tag', 'recipients')
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

            $conversation->update(['last_message_id' => $message->id]);
            $conversation->participants()->whereNull('joined_at')->update(['joined_at' => now()]);

            broadcast(new MessageSent($message->load('user')))->toOthers();

            $participants = $conversation->participants->where('user_id', '!=', $user->id)->pluck('user');
            foreach ($participants as $participant) {
                $participant->notify(new NewMessageNotification($message));
            }

            return $message;
        });
    }
}

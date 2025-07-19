<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

            $participantIds = $conversation->participants()->pluck('user_id');
            $readReceipts = [];

            foreach ($participantIds as $participantId) {
                $readReceipts[$participantId] = [
                    'status' => $participantId === $user->id ? 'read' : 'sent',
                    'read_at' => $participantId === $user->id ? now() : null,
                ];
            }

            $message->recipients()->attach($readReceipts);

            $conversation->update(['last_message_id' => $message->id]);
            $conversation->participants()->whereNull('joined_at')->update(['joined_at' => now()]);


            broadcast(new MessageSent($message->load('user')))->toOthers();

            return $message;
        });
    }

    public function markMessagesAsRead(Conversation $conversation, User $user):void
    {
        DB::table('message_user')
            ->where('user_id', $user->id)
            ->whereIn('message_id', $conversation->messages()->pluck('id'))
            ->where('status', 'sent')
            ->update([
                'status' => 'read',
                'read_at' => now()
            ]);
    }
}

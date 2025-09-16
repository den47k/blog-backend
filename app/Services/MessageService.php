<?php

namespace App\Services;

use App\Events\ConversationCreatedEvent;
use App\Events\MessageCreatedEvent;
use App\Events\MessageDeletedEvent;
use App\Events\MessageUpdatedEvent;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Repositories\ConversationRedisRepository;
use Illuminate\Support\Facades\DB;

class MessageService
{
    public function getMessagesForConversation(Conversation $conversation, int $perPage = 30)
    {
        return $conversation->messages()
            ->with('user:id,name,tag', 'attachment', 'recipients')
            ->latest()
            ->paginate($perPage);
    }

    public function storeMessage(Conversation $conversation, User $user, array $data): Message
    {
        return DB::transaction(function () use ($conversation, $user, $data) {
            $message = $conversation->messages()->create([
                'user_id' => $user->id,
                'content' => $data['content'] ?? null
            ]);

            if (isset($data['attachment'])) {
                $attachmentsService = app(AttachmentsService::class);
                $attachmentsService->storeForMessage($message, $data['attachment']);
            }

            $conversation->update(['last_message_id' => $message->id]);

            $participant = $conversation->participants()->where('user_id', $user->id)->first();

            if (is_null($participant->joined_at)) {
                $conversation->participants()->whereNull('joined_at')->update(['joined_at' => now()]);

                $recipients = $conversation->participants()
                    ->where('user_id', '!=', $user->id)
                    ->with('user')
                    ->get()
                    ->pluck('user');

                if ($recipients->isNotEmpty()) {
                    $conversation->load(['participants.user', 'lastMessage']);

                    foreach ($recipients as $recipient) {
                        event(new ConversationCreatedEvent($conversation, $recipient));
                    }
                }
            }

            $conversationRedisRepository = app(ConversationRedisRepository::class);
            $conversationRedisRepository->markAsRead($conversation, $user);

            $recipients = $conversation->participants->where('user_id', '!=', $user->id)->pluck('user');
            broadcast(new MessageCreatedEvent($message->load('user', 'attachment', 'recipients'), $recipients->all()))->toOthers();

            return $message;
        });
    }

    public function updateMessage(Message $message, array $data)
    {
        return DB::transaction(function () use ($message, $data) {
            $message->update([
                'content' => $data['content'],
                'edited_at' => now()
            ]);

            $conversation = $message->conversation;
            $recipients = $conversation->participants->where('user_id', '!=', $message->user_id)->pluck('user');

            broadcast(new MessageUpdatedEvent($message->load('user', 'attachment', 'recipients'), $recipients->all()))->toOthers();

            return $message;
        });
    }

    public function deleteMessage(Conversation $conversation, Message $message)
    {
        return DB::transaction(function () use ($conversation, $message) {
            $wasLastMessage = $conversation->last_message_id === $message->id;
            $messageId = $message->id;
            $newLastMessage = null;

            if ($wasLastMessage) {
                $newLastMessage = $conversation->messages()
                    ->where('id', '!=', $message->id)
                    ->latest()
                    ->first();

                $conversation->update([
                    'last_message_id' => $newLastMessage?->id,
                ]);
            }

            if ($message->attachment) {
                $attachmentsService = app(AttachmentsService::class);
                $attachmentsService->deleteFiles(collect([$message->attachment]));
            }

            $message->delete();

            $recipients = $conversation->participants
                ->where('user_id', '!=', $message->user_id)
                ->pluck('user');

            broadcast(new MessageDeletedEvent(
                $conversation,
                $messageId,
                $wasLastMessage,
                $newLastMessage,
                $recipients->all()
            ))->toOthers();

            return [
                'deletedId' => $messageId,
                'wasLastMessage' => $wasLastMessage,
                'newLastMessage' => $newLastMessage
                    ? new MessageResource($newLastMessage)
                    : null
            ];
        });
    }
}

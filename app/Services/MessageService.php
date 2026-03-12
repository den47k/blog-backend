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
use App\Repositories\Interfaces\ConversationReadRepositoryInterface;
use App\Repositories\Interfaces\ConversationRepositoryInterface;
use App\Repositories\Interfaces\MessageRepositoryInterface;
use App\Repositories\Interfaces\ParticipantRepositoryInterface;
use Illuminate\Support\Facades\DB;

class MessageService
{
    public function __construct(
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly ConversationRepositoryInterface $conversationRepository,
        private readonly ParticipantRepositoryInterface $participantRepository,
        private readonly ConversationReadRepositoryInterface $readRepository,
        private readonly AttachmentsService $attachmentsService,
    ) {
    }

    public function getMessagesForConversation(Conversation $conversation, int $perPage = 30)
    {
        return $this->messageRepository->getPaginated($conversation, $perPage);
    }

    public function storeMessage(Conversation $conversation, User $user, array $data): Message
    {
        return DB::transaction(function () use ($conversation, $user, $data) {
            $message = $this->messageRepository->create($conversation, $user->id, $data['content'] ?? null);

            if (isset($data['attachment'])) {
                $this->attachmentsService->storeForMessage($message, $data['attachment']);
            }

            $this->conversationRepository->updateLastMessage($conversation, $message->id);

            $participant = $this->participantRepository->find($conversation, $user->id);
            $recipients = $this->participantRepository->getOtherParticipants($conversation, $user->id);

            if (is_null($participant->joined_at)) {
                $this->participantRepository->markUnjoinedAsJoined($conversation);

                if ($recipients->isNotEmpty()) {
                    $conversation->load(['participants.user', 'lastMessage']);

                    foreach ($recipients as $recipient) {
                        event(new ConversationCreatedEvent($conversation, $recipient));
                    }
                }
            }

            $this->readRepository->markAsRead($conversation, $user);

            broadcast(new MessageCreatedEvent($message->load('user', 'attachment', 'recipients'), $recipients->all()))->toOthers();

            return $message;
        });
    }

    public function updateMessage(Message $message, array $data)
    {
        return DB::transaction(function () use ($message, $data) {
            $this->messageRepository->update($message, [
                'content' => $data['content'],
                'edited_at' => now()
            ]);

            $conversation = $message->conversation;
            $recipients = $this->participantRepository->getOtherParticipants($conversation, $message->user_id);

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
                $newLastMessage = $this->messageRepository->findLatestExcluding($conversation, $message->id);

                $this->conversationRepository->updateLastMessage($conversation, $newLastMessage?->id);
            }

            if ($message->attachment) {
                $this->attachmentsService->deleteFile($message->attachment);
            }

            $this->messageRepository->delete($message);

            $recipients = $this->participantRepository->getOtherParticipants($conversation, $message->user_id);

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

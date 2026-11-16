<?php

namespace App\Services\Messaging;

use App\Events\Conversation\ConversationCreatedEvent;
use App\Events\Message\MessageCreatedEvent;
use App\Events\Message\MessageDeletedEvent;
use App\Events\Message\MessageUpdatedEvent;
use App\Http\Resources\Message\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Repositories\ConversationReadRepository;
use App\Repositories\ConversationRepository;
use App\Repositories\MessageRepository;
use App\Repositories\ParticipantRepository;
use Illuminate\Support\Facades\DB;
use Throwable;

class MessageService
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly ConversationRepository $conversationRepository,
        private readonly ParticipantRepository $participantRepository,
        private readonly ConversationReadRepository $readRepository,
        private readonly AttachmentsService $attachmentsService,
        private readonly ConversationService $conversationService,
    ) {
    }

    public function getMessagesForConversation(Conversation $conversation, int $perPage = 30)
    {
        return $this->messageRepository->getPaginated($conversation, $perPage);
    }

    public function storeMessage(Conversation $conversation, User $user, array $data): Message
    {
        // The id is generated up front so the file can be uploaded to its final
        // path before the transaction: the upload must not run under the row lock.
        $messageId = (new Message)->newUniqueId();
        $fileData = isset($data['attachment'])
            ? $this->attachmentsService->upload($conversation->id, $messageId, $data['attachment'])
            : null;

        try {
            $message = DB::transaction(function () use ($conversation, $user, $data, $messageId, $fileData) {
                $this->conversationRepository->lockForUpdate($conversation);

                $message = $this->messageRepository->create($conversation, $user->id, $data['content'] ?? null, $messageId);

                if ($fileData !== null) {
                    $this->messageRepository->addAttachment($message, $fileData);
                }

                $this->conversationRepository->updateLastMessage($conversation, $message->id);
                $conversation->setRelation('lastMessage', $message);

                $conversation->loadMissing('participants.user');
                $participants = $conversation->participants;
                $participant = $participants->firstWhere('user_id', $user->id);
                $recipients = $participants->where('user_id', '!=', $user->id)->pluck('user');

                if (is_null($participant->joined_at)) {
                    $this->participantRepository->markUnjoinedAsJoined($conversation);

                    if ($recipients->isNotEmpty()) {
                        $conversation->load(['participants.user', 'lastMessage']);

                        foreach ($recipients as $recipient) {
                            event(new ConversationCreatedEvent($conversation, $recipient));
                        }
                    }
                }

                broadcast(new MessageCreatedEvent($message->load('user', 'attachment'), $recipients->all()))->toOthers();

                return $message;
            });
        } catch (Throwable $e) {
            if ($fileData !== null) {
                $this->attachmentsService->deletePaths($fileData);
            }

            throw $e;
        }

        $this->readRepository->markAsRead($conversation, $user);

        return $message;
    }

    public function updateMessage(Message $message, array $data)
    {
        return DB::transaction(function () use ($message, $data) {
            $this->messageRepository->update($message, [
                'content' => $data['content'],
            ]);

            $conversation = $message->conversation;
            $recipients = $this->participantRepository->getOtherParticipants($conversation, $message->user_id);

            broadcast(new MessageUpdatedEvent($message->load('user', 'attachment'), $recipients->all()))->toOthers();

            return $message;
        });
    }

    public function deleteMessage(Conversation $conversation, Message $message)
    {
        $attachment = $message->attachment;

        $result = DB::transaction(function () use ($conversation, $message) {
            $this->conversationRepository->lockForUpdate($conversation);

            $wasLastMessage = $conversation->last_message_id === $message->id;
            $messageId = $message->id;
            $newLastMessage = null;

            if ($wasLastMessage) {
                $newLastMessage = $this->messageRepository->findLatestExcluding($conversation, $message->id);

                $this->conversationRepository->updateLastMessage($conversation, $newLastMessage?->id);
            }

            $this->messageRepository->delete($message);

            if ($wasLastMessage && $newLastMessage) {
                $conversation->setRelation('lastMessage', $newLastMessage);
            }

            $recipients = $this->participantRepository->getOtherParticipants($conversation, $message->user_id);

            foreach ($recipients as $recipient) {
                $hasUnread = $this->conversationService->hasUnreadMessages($conversation, $recipient);

                broadcast(new MessageDeletedEvent(
                    $conversation,
                    $messageId,
                    $wasLastMessage,
                    $newLastMessage,
                    $recipient,
                    $hasUnread,
                ))->toOthers();
            }

            return [
                'deletedId' => $messageId,
                'wasLastMessage' => $wasLastMessage,
                'newLastMessage' => $newLastMessage
                    ? new MessageResource($newLastMessage)
                    : null,
            ];
        });

        if ($attachment) {
            $this->attachmentsService->deleteFile($attachment);
        }

        return $result;
    }
}

<?php

namespace App\Services\Messaging;

use App\Events\Conversation\ConversationCreated;
use App\Events\Message\MessageCreated;
use App\Events\Message\MessageDeleted;
use App\Events\Message\MessageUpdated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Repositories\Eloquent\ConversationRepository;
use App\Repositories\Eloquent\MessageRepository;
use App\Repositories\Eloquent\ParticipantRepository;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Throwable;

class MessageService
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly ConversationRepository $conversationRepository,
        private readonly ParticipantRepository $participantRepository,
        private readonly AttachmentService $attachmentService,
    ) {}

    public function getMessagesForConversation(Conversation $conversation, int $perPage = 30): CursorPaginator
    {
        return $this->messageRepository->getPaginated($conversation, $perPage);
    }

    public function storeMessage(Conversation $conversation, User $user, array $data): Message
    {
        // The id is generated up front so the file can be uploaded to its final
        // path before the transaction: the upload must not run under the row lock.
        $messageId = (new Message)->newUniqueId();
        $fileData = isset($data['attachment'])
            ? $this->attachmentService->upload($conversation->id, $messageId, $data['attachment'])
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

                // The message's own timestamp, not now(): the sender's read mark
                // must never fall behind the message it was written for.
                $this->participantRepository->markRead($conversation, $user->id, $message->created_at);

                $conversation->loadMissing('participants.user');
                $participants = $conversation->participants;
                $participant = $participants->firstWhere('user_id', $user->id);
                $recipients = $participants->where('user_id', '!=', $user->id)->pluck('user');

                if (is_null($participant->joined_at)) {
                    $this->participantRepository->markUnjoinedAsJoined($conversation);

                    if ($recipients->isNotEmpty()) {
                        $conversation->load(['participants.user', 'lastMessage']);

                        foreach ($recipients as $recipient) {
                            event(new ConversationCreated($conversation, $recipient, hasUnread: true));
                        }
                        event(new ConversationCreated($conversation, $user, hasUnread: false));
                    }
                }

                broadcast(new MessageCreated(
                    $message->load('user', 'attachment'),
                    $participants->pluck('user')->all(),
                ));

                return $message;
            });
        } catch (Throwable $e) {
            if ($fileData !== null) {
                $this->attachmentService->deletePaths($fileData);
            }

            throw $e;
        }

        return $message;
    }

    public function updateMessage(Message $message, array $data): Message
    {
        return DB::transaction(function () use ($message, $data) {
            $this->messageRepository->update($message, [
                'content' => $data['content'],
            ]);

            $participants = $this->participantRepository->getUsers($message->conversation);

            broadcast(new MessageUpdated($message->load('user', 'attachment'), $participants->all()));

            return $message;
        });
    }

    public function deleteMessage(Conversation $conversation, Message $message): DeletedMessage
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

            foreach ($this->participantRepository->getUsers($conversation) as $participant) {
                broadcast(new MessageDeleted(
                    $conversation,
                    $messageId,
                    $wasLastMessage,
                    $newLastMessage,
                    $participant,
                    $this->conversationRepository->hasUnread($conversation, $participant->id),
                ));
            }

            return new DeletedMessage($messageId, $wasLastMessage, $newLastMessage);
        });

        if ($attachment) {
            $this->attachmentService->deleteFile($attachment);
        }

        return $result;
    }
}

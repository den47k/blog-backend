<?php

namespace Tests\Concerns;

/**
 * JSON shapes the web client validates with zod
 * (wisp-app/packages/domain/src/{user,message,conversation}.ts).
 */
trait AssertsApiContract
{
    protected const USER_KEYS = ['id', 'name', 'tag', 'avatar'];

    /** Message as embedded in lists, responses and realtime payloads. */
    protected const MESSAGE_KEYS = [
        'id',
        'content',
        'conversationId',
        'createdAt',
        'updatedAt',
        'sender' => self::USER_KEYS,
        'attachment',
    ];

    /** `lastMessage` / `newLastMessage`: loaded without sender and attachment today. */
    protected const BARE_MESSAGE_KEYS = ['id', 'content', 'conversationId', 'createdAt', 'updatedAt'];

    /** Conversation without `lastMessage`, which is omitted when the relation is not loaded. */
    protected const CONVERSATION_KEYS = [
        'id',
        'userTag',
        'title',
        'hasUnread',
        'avatar',
        'type',
        'participants' => ['*' => self::USER_KEYS],
        'createdAt',
        'updatedAt',
    ];

    protected const ATTACHMENT_KEYS = ['id', 'data', 'urls'];

    protected function assertIsoDateTime(mixed $value): void
    {
        $this->assertIsString($value);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/',
            $value,
            'Timestamp must satisfy zod z.string().datetime({ offset: true }).',
        );
    }
}

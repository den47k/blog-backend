<?php

namespace Tests\Feature\Realtime;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\AssertsApiContract;
use Tests\TestCase;

/**
 * Pins the realtime wire format: what is actually POSTed to Centrifugo's
 * /api/broadcast for every messaging action. The web client's dispatcher
 * (wisp-app/apps/web/src/features/chat/realtime/dispatcher.ts) switches on
 * `data.event` and reads `data.data`.
 */
class BroadcastTest extends TestCase
{
    use AssertsApiContract;

    private User $author;

    private User $recipient;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create();
        $this->recipient = User::factory()->create();
        $this->conversation = $this->privateConversation($this->author, $this->recipient);
    }

    /** @return list<string> */
    private function conversationAndRecipientChannels(): array
    {
        return ["conv:{$this->conversation->id}", "user:{$this->recipient->id}"];
    }

    private function assertKeys(array $expected, array $actual): void
    {
        $this->assertEqualsCanonicalizing($expected, array_keys($actual));
    }

    // ------------------------------------------------------- message created

    public function test_sending_a_message_publishes_message_created_to_the_conversation_and_the_recipient(): void
    {
        $messageId = $this->actingAsUser($this->author)
            ->postJson("/api/conversations/{$this->conversation->id}/messages", ['content' => 'hello'])
            ->assertCreated()
            ->json('data.message.id');

        $publications = $this->centrifugoPublications();
        $this->assertCount(1, $publications);

        $publication = $publications[0];
        $this->assertSame($this->conversationAndRecipientChannels(), $publication['channels']);
        $this->assertKeys(['event', 'data'], $publication['data']);
        $this->assertSame('MessageCreatedEvent', $publication['data']['event']);
        $this->assertKeys(['message'], $publication['data']['data']);

        $message = $publication['data']['data']['message'];
        $this->assertKeys(
            ['id', 'content', 'conversationId', 'createdAt', 'updatedAt', 'sender', 'attachment'],
            $message,
        );
        $this->assertSame($messageId, $message['id']);
        $this->assertSame('hello', $message['content']);
        $this->assertSame($this->conversation->id, $message['conversationId']);
        $this->assertIsoDateTime($message['createdAt']);
        $this->assertIsoDateTime($message['updatedAt']);
        $this->assertNull($message['attachment']);
        $this->assertKeys(self::USER_KEYS, $message['sender']);
        $this->assertSame($this->author->id, $message['sender']['id']);
        $this->assertSame($this->author->name, $message['sender']['name']);
        $this->assertSame($this->author->tag, $message['sender']['tag']);
    }

    public function test_publications_are_sent_to_the_centrifugo_http_api_with_the_api_key(): void
    {
        $this->actingAsUser($this->author)
            ->postJson("/api/conversations/{$this->conversation->id}/messages", ['content' => 'hello'])
            ->assertCreated();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'http://centrifugo.test/api/broadcast'
            && $request->hasHeader('X-API-Key', 'test-api-key')
            && $request->isJson());
    }

    public function test_the_socket_id_header_is_forwarded_as_origin_client(): void
    {
        $this->actingAsUser($this->author)
            ->postJson(
                "/api/conversations/{$this->conversation->id}/messages",
                ['content' => 'hello'],
                ['X-Socket-ID' => 'client-123'],
            )
            ->assertCreated();

        $publication = $this->centrifugoPublications()[0];

        $this->assertKeys(['event', 'data', '_origin_client'], $publication['data']);
        $this->assertSame('client-123', $publication['data']['_origin_client']);
        $this->assertKeys(['message'], $publication['data']['data']);
    }

    public function test_message_created_carries_the_attachment(): void
    {
        $this->actingAsUser($this->author)
            ->post("/api/conversations/{$this->conversation->id}/messages", [
                'attachment' => UploadedFile::fake()->image('photo.jpg', 640, 480),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $message = $this->centrifugoPublicationsOf('MessageCreatedEvent')[0]['data']['data']['message'];

        $this->assertNull($message['content']);
        $this->assertKeys(self::ATTACHMENT_KEYS, $message['attachment']);
        $this->assertSame('image', $message['attachment']['data']['type']);
        $this->assertKeys(['original', 'thumbnail'], $message['attachment']['urls']);
    }

    // -------------------------------------------------- conversation created

    public function test_first_message_publishes_conversation_created_to_the_recipient_before_the_message(): void
    {
        $recipient = User::factory()->create();
        $conversation = $this->privateConversation($this->author, $recipient, joined: false);

        $messageId = $this->actingAsUser($this->author)
            ->postJson("/api/conversations/{$conversation->id}/messages", ['content' => 'first!'])
            ->assertCreated()
            ->json('data.message.id');

        $publications = $this->centrifugoPublications();
        $this->assertSame(
            ['ConversationCreated', 'MessageCreatedEvent'],
            array_map(fn (array $publication) => $publication['data']['event'], $publications),
        );

        [$created, $message] = $publications;

        $this->assertSame(["user:{$recipient->id}"], $created['channels']);
        $this->assertKeys(['event', 'data'], $created['data']);
        $this->assertKeys(['conversation'], $created['data']['data']);

        $payload = $created['data']['data']['conversation'];
        $this->assertKeys(
            ['id', 'userTag', 'title', 'lastMessage', 'hasUnread', 'avatar', 'type', 'participants', 'createdAt', 'updatedAt'],
            $payload,
        );
        $this->assertSame($conversation->id, $payload['id']);
        $this->assertSame('private', $payload['type']);
        // Rendered from the recipient's point of view: the "other" side is the author.
        $this->assertSame($this->author->tag, $payload['userTag']);
        $this->assertSame($this->author->name, $payload['title']);
        $this->assertIsBool($payload['hasUnread']);
        $this->assertSame($messageId, $payload['lastMessage']['id']);
        $this->assertSame('first!', $payload['lastMessage']['content']);
        $this->assertIsoDateTime($payload['createdAt']);
        $this->assertIsoDateTime($payload['updatedAt']);
        $this->assertCount(2, $payload['participants']);
        foreach ($payload['participants'] as $participant) {
            $this->assertKeys(self::USER_KEYS, $participant);
        }
        $this->assertEqualsCanonicalizing(
            [$this->author->id, $recipient->id],
            array_column($payload['participants'], 'id'),
        );

        $this->assertSame(["conv:{$conversation->id}", "user:{$recipient->id}"], $message['channels']);
        $this->assertSame($messageId, $message['data']['data']['message']['id']);
    }

    public function test_later_messages_do_not_publish_conversation_created(): void
    {
        $this->actingAsUser($this->author)
            ->postJson("/api/conversations/{$this->conversation->id}/messages", ['content' => 'again'])
            ->assertCreated();

        $this->assertSame([], $this->centrifugoPublicationsOf('ConversationCreated'));
    }

    // ------------------------------------------------------- message updated

    public function test_updating_a_message_publishes_message_updated_to_the_same_channels(): void
    {
        $message = $this->message($this->conversation, $this->author, ['content' => 'before']);

        $this->actingAsUser($this->author)
            ->patchJson("/api/conversations/{$this->conversation->id}/messages/{$message->id}", ['content' => 'after'])
            ->assertOk();

        $publications = $this->centrifugoPublications();
        $this->assertCount(1, $publications);

        $publication = $publications[0];
        $this->assertSame($this->conversationAndRecipientChannels(), $publication['channels']);
        $this->assertKeys(['event', 'data'], $publication['data']);
        $this->assertSame('MessageUpdatedEvent', $publication['data']['event']);
        $this->assertKeys(['message'], $publication['data']['data']);

        $payload = $publication['data']['data']['message'];
        $this->assertKeys(
            ['id', 'content', 'conversationId', 'createdAt', 'updatedAt', 'sender', 'attachment'],
            $payload,
        );
        $this->assertSame($message->id, $payload['id']);
        $this->assertSame('after', $payload['content']);
        $this->assertSame($this->conversation->id, $payload['conversationId']);
        $this->assertSame($this->author->id, $payload['sender']['id']);
        $this->assertNull($payload['attachment']);
    }

    // ------------------------------------------------------- message deleted

    public function test_deleting_the_last_message_publishes_message_deleted_with_the_new_last_message(): void
    {
        $previous = $this->message($this->conversation, $this->recipient, [
            'content' => 'previous',
            'created_at' => now()->subMinutes(2),
        ]);
        $last = $this->message($this->conversation, $this->author, ['created_at' => now()->subMinute()]);

        $this->actingAsUser($this->author)
            ->deleteJson("/api/conversations/{$this->conversation->id}/messages/{$last->id}")
            ->assertOk();

        $publications = $this->centrifugoPublications();
        $this->assertCount(1, $publications);

        $publication = $publications[0];
        $this->assertSame($this->conversationAndRecipientChannels(), $publication['channels']);
        $this->assertKeys(['event', 'data'], $publication['data']);
        $this->assertSame('MessageDeletedEvent', $publication['data']['event']);

        $payload = $publication['data']['data'];
        $this->assertKeys(['conversationId', 'deletedId', 'wasLastMessage', 'newLastMessage', 'hasUnread'], $payload);
        $this->assertSame($this->conversation->id, $payload['conversationId']);
        $this->assertSame($last->id, $payload['deletedId']);
        $this->assertTrue($payload['wasLastMessage']);
        $this->assertSame($previous->id, $payload['newLastMessage']['id']);
        $this->assertSame('previous', $payload['newLastMessage']['content']);
        $this->assertSame($this->conversation->id, $payload['newLastMessage']['conversationId']);
        $this->assertIsoDateTime($payload['newLastMessage']['createdAt']);
        // The recipient never opened the conversation, so what is left is still unread for them.
        $this->assertTrue($payload['hasUnread']);
    }

    public function test_message_deleted_reports_has_unread_false_when_the_recipient_already_read_the_rest(): void
    {
        $this->message($this->conversation, $this->author, ['created_at' => now()->subMinutes(3)]);
        $last = $this->message($this->conversation, $this->author, ['created_at' => now()->subMinutes(2)]);

        $this->actingAsUser($this->recipient)
            ->postJson("/api/conversations/{$this->conversation->id}/mark-as-read")
            ->assertOk();

        $this->actingAsUser($this->author)
            ->deleteJson("/api/conversations/{$this->conversation->id}/messages/{$last->id}")
            ->assertOk();

        $payload = $this->centrifugoPublicationsOf('MessageDeletedEvent')[0]['data']['data'];

        $this->assertTrue($payload['wasLastMessage']);
        $this->assertFalse($payload['hasUnread']);
    }

    public function test_deleting_a_non_last_message_publishes_message_deleted_without_a_new_last_message(): void
    {
        $first = $this->message($this->conversation, $this->author, ['created_at' => now()->subMinutes(2)]);
        $this->message($this->conversation, $this->recipient, ['created_at' => now()->subMinute()]);

        $this->actingAsUser($this->author)
            ->deleteJson("/api/conversations/{$this->conversation->id}/messages/{$first->id}")
            ->assertOk();

        $publications = $this->centrifugoPublications();
        $this->assertCount(1, $publications);
        $this->assertSame($this->conversationAndRecipientChannels(), $publications[0]['channels']);

        $payload = $publications[0]['data']['data'];
        $this->assertKeys(['conversationId', 'deletedId', 'wasLastMessage', 'newLastMessage', 'hasUnread'], $payload);
        $this->assertSame($first->id, $payload['deletedId']);
        $this->assertFalse($payload['wasLastMessage']);
        $this->assertNull($payload['newLastMessage']);
        $this->assertIsBool($payload['hasUnread']);
    }

    public function test_deleting_the_only_message_publishes_message_deleted_with_nothing_left(): void
    {
        $only = $this->message($this->conversation, $this->author);

        $this->actingAsUser($this->author)
            ->deleteJson("/api/conversations/{$this->conversation->id}/messages/{$only->id}")
            ->assertOk();

        $payload = $this->centrifugoPublicationsOf('MessageDeletedEvent')[0]['data']['data'];

        $this->assertSame($only->id, $payload['deletedId']);
        $this->assertTrue($payload['wasLastMessage']);
        $this->assertNull($payload['newLastMessage']);
        $this->assertFalse($payload['hasUnread']);
    }

    // -------------------------------------------------- conversation deleted

    public function test_deleting_a_conversation_publishes_conversation_deleted_to_the_other_joined_participant(): void
    {
        $this->actingAsUser($this->author)
            ->deleteJson("/api/conversations/{$this->conversation->id}")
            ->assertOk();

        $publications = $this->centrifugoPublications();
        $this->assertCount(1, $publications);

        $publication = $publications[0];
        $this->assertSame(["user:{$this->recipient->id}"], $publication['channels']);
        $this->assertKeys(['event', 'data'], $publication['data']);
        $this->assertSame('ConversationDeleted', $publication['data']['event']);
        $this->assertSame(['id' => $this->conversation->id], $publication['data']['data']);
    }

    public function test_deleting_a_conversation_nobody_else_joined_publishes_nothing(): void
    {
        $other = User::factory()->create();
        $conversation = $this->privateConversation($this->author, $other, joined: false);

        $this->actingAsUser($this->author)
            ->deleteJson("/api/conversations/{$conversation->id}")
            ->assertOk();

        $this->assertSame([], $this->centrifugoPublications());
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------ rollback

    public function test_a_send_that_rolls_back_publishes_nothing_and_leaves_no_trace(): void
    {
        $recipient = User::factory()->create();
        $conversation = $this->privateConversation($this->author, $recipient, joined: false);

        // Fails after the message row is written and both events are raised.
        Conversation::updating(fn () => throw new RuntimeException('forced'));

        $this->actingAsUser($this->author)
            ->post("/api/conversations/{$conversation->id}/messages", [
                'attachment' => UploadedFile::fake()->image('photo.jpg', 640, 480),
            ], ['Accept' => 'application/json'])
            ->assertServerError();

        Http::assertNothingSent();
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('message_attachments', 0);
        $this->assertSame([], Storage::disk('s3')->allFiles());
    }

    public function test_a_delete_that_rolls_back_publishes_nothing_and_keeps_the_files(): void
    {
        $messageId = $this->actingAsUser($this->author)
            ->post("/api/conversations/{$this->conversation->id}/messages", [
                'attachment' => UploadedFile::fake()->image('photo.jpg', 640, 480),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.message.id');

        Message::deleting(fn () => throw new RuntimeException('forced'));

        $this->deleteJson("/api/conversations/{$this->conversation->id}/messages/{$messageId}")
            ->assertServerError();

        $this->assertSame([], $this->centrifugoPublicationsOf('MessageDeletedEvent'));
        $this->assertDatabaseHas('messages', ['id' => $messageId]);
        $this->assertSame($messageId, $this->conversation->fresh()->last_message_id);
        $this->assertCount(2, Storage::disk('s3')->allFiles());
    }

    // ----------------------------------------------------- nothing published

    public function test_reads_and_rejected_writes_publish_nothing(): void
    {
        $message = $this->message($this->conversation, $this->author);
        $base = "/api/conversations/{$this->conversation->id}";

        $this->actingAsUser($this->recipient);
        $this->getJson('/api/conversations')->assertOk();
        $this->getJson("{$base}/messages")->assertOk();
        $this->postJson("{$base}/mark-as-read")->assertOk();
        $this->postJson("{$base}/messages", [])->assertUnprocessable();
        $this->patchJson("{$base}/messages/{$message->id}", ['content' => 'nope'])->assertForbidden();
        $this->deleteJson("{$base}/messages/{$message->id}")->assertForbidden();

        $this->actingAsUser(User::factory()->create());
        $this->postJson("{$base}/messages", ['content' => 'nope'])->assertForbidden();
        $this->deleteJson($base)->assertForbidden();

        Http::assertNothingSent();
    }
}

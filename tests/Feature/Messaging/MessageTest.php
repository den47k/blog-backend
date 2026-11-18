<?php

namespace Tests\Feature\Messaging;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AssertsApiContract;
use Tests\TestCase;

class MessageTest extends TestCase
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

    private function messagesUrl(?Message $message = null): string
    {
        return "/api/conversations/{$this->conversation->id}/messages".($message ? "/{$message->id}" : '');
    }

    // ---------------------------------------------------------------- index

    public function test_index_paginates_30_per_page_newest_first_with_a_cursor(): void
    {
        $base = now()->subHour()->startOfSecond();
        $messages = Message::factory()
            ->count(35)
            ->for($this->conversation)
            ->for($this->author)
            ->state(new Sequence(fn (Sequence $sequence) => [
                'content' => "message {$sequence->index}",
                'created_at' => $base->copy()->addSeconds($sequence->index),
            ]))
            ->create();
        $newestFirst = $messages->reverse()->pluck('id')->values();

        $this->actingAsUser($this->recipient);

        $firstPage = $this->getJson($this->messagesUrl())
            ->assertOk()
            ->assertJsonCount(30, 'data')
            ->assertJsonStructure([
                'data' => ['*' => self::MESSAGE_KEYS],
                'links' => ['next'],
                'meta' => ['next_cursor'],
            ]);

        $this->assertSame($newestFirst->take(30)->all(), $firstPage->json('data.*.id'));
        $cursor = $firstPage->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        $secondPage = $this->getJson($this->messagesUrl().'?cursor='.urlencode($cursor))
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.next_cursor', null);

        $this->assertSame($newestFirst->skip(30)->values()->all(), $secondPage->json('data.*.id'));
    }

    public function test_index_cursor_tells_apart_messages_sent_within_the_same_second(): void
    {
        $base = now()->subHour()->startOfSecond();
        $messages = Message::factory()
            ->count(35)
            ->for($this->conversation)
            ->for($this->author)
            ->state(new Sequence(fn (Sequence $sequence) => [
                'created_at' => $base->copy()->addMilliseconds($sequence->index * 10),
            ]))
            ->create();
        $newestFirst = $messages->reverse()->pluck('id')->values();

        $this->actingAsUser($this->recipient);

        $firstPage = $this->getJson($this->messagesUrl())->assertOk();
        $this->assertSame($newestFirst->take(30)->all(), $firstPage->json('data.*.id'));

        $secondPage = $this->getJson($this->messagesUrl().'?cursor='.urlencode($firstPage->json('meta.next_cursor')))
            ->assertOk();
        $this->assertSame($newestFirst->skip(30)->values()->all(), $secondPage->json('data.*.id'));
    }

    public function test_index_items_match_the_client_contract(): void
    {
        $message = $this->message($this->conversation, $this->author, ['content' => 'hello']);

        $item = $this->actingAsUser($this->recipient)
            ->getJson($this->messagesUrl())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data.0');

        $this->assertSame($message->id, $item['id']);
        $this->assertSame('hello', $item['content']);
        $this->assertSame($this->conversation->id, $item['conversationId']);
        $this->assertIsoDateTime($item['createdAt']);
        $this->assertIsoDateTime($item['updatedAt']);
        $this->assertSame($this->author->id, $item['sender']['id']);
        $this->assertSame($this->author->name, $item['sender']['name']);
        $this->assertSame($this->author->tag, $item['sender']['tag']);
        $this->assertNull($item['attachment']);
    }

    public function test_index_is_forbidden_for_a_non_participant(): void
    {
        $this->actingAsUser(User::factory()->create())
            ->getJson($this->messagesUrl())
            ->assertForbidden();
    }

    // ---------------------------------------------------------------- store

    public function test_store_creates_a_message_and_makes_it_the_last_message(): void
    {
        $response = $this->actingAsUser($this->author)
            ->postJson($this->messagesUrl(), ['content' => 'hello there'])
            ->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'message' => self::MESSAGE_KEYS,
                    'conversation' => [...self::CONVERSATION_KEYS, 'lastMessage' => self::MESSAGE_KEYS],
                ],
            ])
            ->assertJsonMissingPath('message')
            ->assertJsonPath('data.message.content', 'hello there')
            ->assertJsonPath('data.message.conversationId', $this->conversation->id)
            ->assertJsonPath('data.message.sender.id', $this->author->id)
            ->assertJsonPath('data.message.attachment', null)
            ->assertJsonPath('data.conversation.id', $this->conversation->id)
            ->assertJsonPath('data.conversation.userTag', $this->recipient->tag);

        $messageId = $response->json('data.message.id');

        $this->assertSame($response->json('data.message'), $response->json('data.conversation.lastMessage'));
        $this->assertIsoDateTime($response->json('data.message.createdAt'));
        $this->assertDatabaseHas('messages', [
            'id' => $messageId,
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->author->id,
            'content' => 'hello there',
        ]);
        $this->assertSame($messageId, $this->conversation->fresh()->last_message_id);
    }

    public function test_store_leaves_the_conversation_read_for_the_sender_and_unread_for_the_recipient(): void
    {
        $this->actingAsUser($this->author)
            ->postJson($this->messagesUrl(), ['content' => 'ping'])
            ->assertCreated();

        $this->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.hasUnread', false);

        $this->actingAsUser($this->recipient)
            ->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.hasUnread', true);
    }

    public function test_a_message_sent_in_the_same_second_as_the_read_mark_is_unread(): void
    {
        $this->travelTo(now()->startOfSecond()->addMilliseconds(100));

        $this->actingAsUser($this->recipient)
            ->postJson("/api/conversations/{$this->conversation->id}/read")
            ->assertNoContent();

        $this->travelTo(now()->addMilliseconds(200));
        $this->message($this->conversation, $this->author);

        $this->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.hasUnread', true);
    }

    public function test_first_message_makes_the_conversation_visible_to_both_participants(): void
    {
        $recipient = User::factory()->create();
        $conversation = $this->privateConversation($this->author, $recipient, joined: false);

        $this->actingAsUser($this->author)
            ->postJson("/api/conversations/{$conversation->id}/messages", ['content' => 'first!'])
            ->assertCreated();

        $this->assertDatabaseMissing('participants', [
            'conversation_id' => $conversation->id,
            'joined_at' => null,
        ]);

        $this->actingAsUser($recipient)
            ->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conversation->id)
            ->assertJsonPath('data.0.lastMessage.content', 'first!');

        $this->actingAsUser($this->author)
            ->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonFragment(['id' => $conversation->id]);
    }

    public function test_store_requires_content_or_attachment(): void
    {
        $this->actingAsUser($this->author)
            ->postJson($this->messagesUrl(), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content', 'attachment']);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_store_rejects_content_longer_than_5000_characters(): void
    {
        $this->actingAsUser($this->author);

        $this->postJson($this->messagesUrl(), ['content' => str_repeat('a', 5001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');

        $this->postJson($this->messagesUrl(), ['content' => str_repeat('a', 5000)])
            ->assertCreated();
    }

    public function test_store_rejects_content_and_attachment_together(): void
    {
        $this->actingAsUser($this->author)
            ->post($this->messagesUrl(), [
                'content' => 'caption',
                'attachment' => UploadedFile::fake()->image('photo.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content', 'attachment']);

        $this->assertDatabaseCount('messages', 0);
        $this->assertSame([], Storage::disk('s3')->allFiles());
    }

    public function test_store_is_forbidden_for_a_non_participant(): void
    {
        $this->actingAsUser(User::factory()->create())
            ->postJson($this->messagesUrl(), ['content' => 'let me in'])
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_store_with_an_image_attachment_uploads_the_file_and_a_thumbnail(): void
    {
        $response = $this->actingAsUser($this->author)
            ->post($this->messagesUrl(), [
                'attachment' => UploadedFile::fake()->image('photo.jpg', 640, 480),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['message' => [
                ...self::MESSAGE_KEYS,
                'attachment' => [
                    'id',
                    'data' => ['original', 'thumbnail', 'type', 'size', 'mime_type', 'original_name'],
                    'urls' => ['original', 'thumbnail'],
                ],
            ]]])
            ->assertJsonPath('data.message.content', null)
            ->assertJsonPath('data.message.attachment.data.type', 'image')
            ->assertJsonPath('data.message.attachment.data.original_name', 'photo.jpg');

        $messageId = $response->json('data.message.id');
        $data = $response->json('data.message.attachment.data');
        $urls = $response->json('data.message.attachment.urls');
        $directory = "attachments/{$this->conversation->id}/{$messageId}/";

        $this->assertStringStartsWith($directory, $data['original']);
        $this->assertStringStartsWith($directory.'thumb_', $data['thumbnail']);
        Storage::disk('s3')->assertExists([$data['original'], $data['thumbnail']]);

        // Scaled down to fit 300x300 without changing the 4:3 aspect ratio.
        [$width, $height] = getimagesizefromstring(Storage::disk('s3')->get($data['thumbnail']));
        $this->assertSame([300, 225], [$width, $height]);

        $this->assertSame(route('api.storage', ['path' => $data['original']]), $urls['original']);
        $this->assertSame(route('api.storage', ['path' => $data['thumbnail']]), $urls['thumbnail']);

        $this->assertDatabaseHas('message_attachments', ['message_id' => $messageId]);
        $this->assertSame($messageId, $this->conversation->fresh()->last_message_id);
    }

    public function test_store_with_a_non_image_attachment_has_no_thumbnail(): void
    {
        $response = $this->actingAsUser($this->author)
            ->post($this->messagesUrl(), [
                'attachment' => UploadedFile::fake()->create('report.pdf', 20, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.message.attachment.data.type', 'file')
            ->assertJsonPath('data.message.attachment.data.original_name', 'report.pdf');

        $data = $response->json('data.message.attachment.data');

        $this->assertArrayNotHasKey('thumbnail', $data);
        $this->assertSame(['original'], array_keys($response->json('data.message.attachment.urls')));
        Storage::disk('s3')->assertExists($data['original']);
        $this->assertCount(1, Storage::disk('s3')->allFiles());
    }

    // --------------------------------------------------------------- update

    public function test_author_can_update_a_message(): void
    {
        $message = $this->message($this->conversation, $this->author, ['content' => 'before']);

        $this->actingAsUser($this->author)
            ->patchJson($this->messagesUrl($message), ['content' => 'after'])
            ->assertOk()
            ->assertJsonStructure(['data' => self::MESSAGE_KEYS])
            ->assertJsonMissingPath('message')
            ->assertJsonPath('data.attachment', null)
            ->assertJsonPath('data.id', $message->id)
            ->assertJsonPath('data.content', 'after')
            ->assertJsonPath('data.conversationId', $this->conversation->id)
            ->assertJsonPath('data.sender.id', $this->author->id);

        $this->assertDatabaseHas('messages', ['id' => $message->id, 'content' => 'after']);
    }

    public function test_other_participant_cannot_update_a_message(): void
    {
        $message = $this->message($this->conversation, $this->author, ['content' => 'before']);

        $this->actingAsUser($this->recipient)
            ->patchJson($this->messagesUrl($message), ['content' => 'hijacked'])
            ->assertForbidden();

        $this->assertDatabaseHas('messages', ['id' => $message->id, 'content' => 'before']);
    }

    public function test_update_validates_content(): void
    {
        $message = $this->message($this->conversation, $this->author, ['content' => 'before']);

        $this->actingAsUser($this->author);

        $this->patchJson($this->messagesUrl($message), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');

        $this->patchJson($this->messagesUrl($message), ['content' => str_repeat('a', 5001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');

        $this->assertDatabaseHas('messages', ['id' => $message->id, 'content' => 'before']);
    }

    // --------------------------------------------------------------- delete

    public function test_deleting_the_last_message_promotes_the_previous_one(): void
    {
        $previous = $this->message($this->conversation, $this->recipient, [
            'content' => 'previous',
            'created_at' => now()->subMinutes(2),
        ]);
        $last = $this->message($this->conversation, $this->author, [
            'content' => 'last',
            'created_at' => now()->subMinute(),
        ]);

        $response = $this->actingAsUser($this->author)
            ->deleteJson($this->messagesUrl($last))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'conversationId',
                    'deletedId',
                    'wasLastMessage',
                    'newLastMessage' => self::MESSAGE_KEYS,
                ],
            ])
            ->assertJsonMissingPath('message')
            ->assertJsonPath('data.conversationId', $this->conversation->id)
            ->assertJsonPath('data.deletedId', $last->id)
            ->assertJsonPath('data.wasLastMessage', true)
            ->assertJsonPath('data.newLastMessage.id', $previous->id)
            ->assertJsonPath('data.newLastMessage.content', 'previous')
            ->assertJsonPath('data.newLastMessage.conversationId', $this->conversation->id)
            ->assertJsonPath('data.newLastMessage.sender.id', $this->recipient->id);

        $this->assertIsoDateTime($response->json('data.newLastMessage.createdAt'));
        $this->assertDatabaseMissing('messages', ['id' => $last->id]);
        $this->assertSame($previous->id, $this->conversation->fresh()->last_message_id);
    }

    public function test_deleting_a_non_last_message_keeps_the_last_message(): void
    {
        $first = $this->message($this->conversation, $this->author, ['created_at' => now()->subMinutes(2)]);
        $last = $this->message($this->conversation, $this->recipient, ['created_at' => now()->subMinute()]);

        $this->actingAsUser($this->author)
            ->deleteJson($this->messagesUrl($first))
            ->assertOk()
            ->assertJsonPath('data.deletedId', $first->id)
            ->assertJsonPath('data.wasLastMessage', false)
            ->assertJsonPath('data.newLastMessage', null);

        $this->assertDatabaseMissing('messages', ['id' => $first->id]);
        $this->assertSame($last->id, $this->conversation->fresh()->last_message_id);
    }

    public function test_deleting_the_only_message_clears_the_last_message(): void
    {
        $only = $this->message($this->conversation, $this->author);

        $this->actingAsUser($this->author)
            ->deleteJson($this->messagesUrl($only))
            ->assertOk()
            ->assertJsonPath('data.wasLastMessage', true)
            ->assertJsonPath('data.newLastMessage', null);

        $this->assertDatabaseCount('messages', 0);
        $this->assertNull($this->conversation->fresh()->last_message_id);

        $this->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.lastMessage', null);
    }

    public function test_deleting_a_message_removes_its_attachment_files(): void
    {
        $this->actingAsUser($this->author);

        $messageId = $this->post($this->messagesUrl(), [
            'attachment' => UploadedFile::fake()->image('photo.jpg', 640, 480),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.message.id');

        $this->assertCount(2, Storage::disk('s3')->allFiles());

        $this->deleteJson($this->messagesUrl(Message::findOrFail($messageId)))->assertOk();

        $this->assertSame([], Storage::disk('s3')->allFiles());
        $this->assertDatabaseCount('message_attachments', 0);
    }

    public function test_other_participant_cannot_delete_a_message(): void
    {
        $message = $this->message($this->conversation, $this->author);

        $this->actingAsUser($this->recipient)
            ->deleteJson($this->messagesUrl($message))
            ->assertForbidden();

        $this->assertDatabaseHas('messages', ['id' => $message->id]);
        $this->assertSame($message->id, $this->conversation->fresh()->last_message_id);
    }

    // ------------------------------------------------------------- scoping

    public function test_a_message_cannot_be_reached_through_another_conversation(): void
    {
        $message = $this->message($this->conversation, $this->author);
        $other = $this->privateConversation($this->author, User::factory()->create());
        $otherLast = $this->message($other, $this->author);
        $url = "/api/conversations/{$other->id}/messages/{$message->id}";

        $this->actingAsUser($this->author);
        $this->patchJson($url, ['content' => 'moved'])->assertNotFound();
        $this->deleteJson($url)->assertNotFound();

        $this->assertDatabaseHas('messages', ['id' => $message->id]);
        $this->assertSame($otherLast->id, $other->fresh()->last_message_id);
    }

    public function test_author_who_left_the_conversation_cannot_update_or_delete_a_message(): void
    {
        $message = $this->message($this->conversation, $this->author);
        $this->conversation->participants()->where('user_id', $this->author->id)->delete();

        $this->actingAsUser($this->author);
        $this->patchJson($this->messagesUrl($message), ['content' => 'still here'])->assertForbidden();
        $this->deleteJson($this->messagesUrl($message))->assertForbidden();

        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }
}

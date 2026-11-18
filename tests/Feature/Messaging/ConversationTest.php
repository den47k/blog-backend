<?php

namespace Tests\Feature\Messaging;

use App\Http\Resources\Conversation\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AssertsApiContract;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use AssertsApiContract;

    // ---------------------------------------------------------------- index

    public function test_index_lists_only_conversations_the_user_has_joined(): void
    {
        $user = User::factory()->create();
        $joined = $this->privateConversation($user, User::factory()->create());
        $this->privateConversation($user, User::factory()->create(), joined: false);
        $this->privateConversation(User::factory()->create(), User::factory()->create());

        $this->actingAsUser($user)
            ->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $joined->id);
    }

    public function test_index_orders_conversations_by_latest_activity(): void
    {
        $user = User::factory()->create();
        $oldest = $this->privateConversation($user, User::factory()->create());
        $newest = $this->privateConversation($user, User::factory()->create());
        $middle = $this->privateConversation($user, User::factory()->create());

        Conversation::whereKey($oldest->id)->update(['updated_at' => now()->subHours(3)]);
        Conversation::whereKey($middle->id)->update(['updated_at' => now()->subHours(2)]);
        Conversation::whereKey($newest->id)->update(['updated_at' => now()->subHour()]);

        $response = $this->actingAsUser($user)->getJson('/api/conversations')->assertOk();

        $this->assertSame(
            [$newest->id, $middle->id, $oldest->id],
            $response->json('data.*.id'),
        );
    }

    public function test_index_matches_the_client_contract(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->privateConversation($user, $other);
        $message = $this->message($conversation, $other, ['content' => 'hello']);

        $response = $this->actingAsUser($user)
            ->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['*' => [...self::CONVERSATION_KEYS, 'lastMessage' => self::MESSAGE_KEYS]],
                'links' => ['next'],
                'meta' => ['next_cursor'],
            ]);

        $item = $response->json('data.0');

        $this->assertSame($conversation->id, $item['id']);
        $this->assertSame('private', $item['type']);
        $this->assertSame($other->tag, $item['userTag']);
        $this->assertSame($other->name, $item['title']);
        $this->assertNull($item['avatar']);
        $this->assertArrayNotHasKey('description', $item);
        $this->assertIsoDateTime($item['createdAt']);
        $this->assertIsoDateTime($item['updatedAt']);

        $this->assertSame($message->id, $item['lastMessage']['id']);
        $this->assertSame('hello', $item['lastMessage']['content']);
        $this->assertSame($conversation->id, $item['lastMessage']['conversationId']);
        $this->assertSame($other->id, $item['lastMessage']['sender']['id']);
        $this->assertNull($item['lastMessage']['attachment']);
        $this->assertIsoDateTime($item['lastMessage']['createdAt']);
        $this->assertIsoDateTime($item['lastMessage']['updatedAt']);

        $this->assertEqualsCanonicalizing(
            [$user->id, $other->id],
            array_column($item['participants'], 'id'),
        );
    }

    public function test_index_returns_null_last_message_for_a_conversation_without_messages(): void
    {
        $user = User::factory()->create();
        $this->privateConversation($user, User::factory()->create());

        $this->actingAsUser($user)
            ->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.lastMessage', null)
            ->assertJsonPath('data.0.hasUnread', false);
    }

    public function test_index_flags_a_conversation_as_unread_until_it_is_marked_as_read(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->privateConversation($user, $other);
        $this->message($conversation, $other, ['created_at' => now()->subMinute()]);

        $this->actingAsUser($user);

        $this->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.hasUnread', true);

        $this->postJson("/api/conversations/{$conversation->id}/mark-as-read")
            ->assertNoContent();

        $this->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.hasUnread', false);
    }

    public function test_read_state_is_tracked_per_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->privateConversation($user, $other);
        $this->message($conversation, $other, ['created_at' => now()->subMinute()]);

        $this->actingAsUser($other)
            ->postJson("/api/conversations/{$conversation->id}/mark-as-read")
            ->assertNoContent();

        $this->actingAsUser($user)
            ->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.hasUnread', true);
    }

    public function test_mark_as_read_stores_the_read_mark_on_the_participant_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->privateConversation($user, $other);

        $this->freezeTime();

        $this->actingAsUser($user)
            ->postJson("/api/conversations/{$conversation->id}/mark-as-read")
            ->assertNoContent();

        $participants = $conversation->participants()->get()->keyBy('user_id');

        $this->assertTrue(now()->equalTo($participants[$user->id]->last_read_at));
        $this->assertNull($participants[$other->id]->last_read_at);
    }

    public function test_mark_as_read_is_forbidden_for_a_non_participant(): void
    {
        $conversation = $this->privateConversation(User::factory()->create(), User::factory()->create());

        $this->actingAsUser(User::factory()->create())
            ->postJson("/api/conversations/{$conversation->id}/mark-as-read")
            ->assertForbidden();
    }

    public function test_resource_renders_for_an_explicit_viewer_without_an_authenticated_user(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->privateConversation($viewer, $other);

        $this->assertGuest();

        $forViewer = (new ConversationResource($conversation, $viewer))->resolve();
        $this->assertSame($other->tag, $forViewer['userTag']);
        $this->assertFalse($forViewer['hasUnread']);

        $forOther = (new ConversationResource($conversation, $other, hasUnread: true))->resolve();
        $this->assertSame($viewer->tag, $forOther['userTag']);
        $this->assertTrue($forOther['hasUnread']);
    }

    // ----------------------------------------------------------- show by tag

    public function test_show_returns_the_private_conversation_with_the_tagged_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->privateConversation($user, $other);

        $this->actingAsUser($user)
            ->getJson("/api/conversations/private/{$other->tag}")
            ->assertOk()
            ->assertJsonStructure(['data' => self::CONVERSATION_KEYS])
            ->assertJsonPath('data.id', $conversation->id)
            ->assertJsonPath('data.userTag', $other->tag);
    }

    public function test_show_returns_404_when_no_conversation_exists_with_that_user(): void
    {
        $other = User::factory()->create();

        $this->actingAsUser(User::factory()->create())
            ->getJson("/api/conversations/private/{$other->tag}")
            ->assertNotFound();
    }

    public function test_show_returns_404_for_an_unknown_tag(): void
    {
        $this->actingAsUser(User::factory()->create())
            ->getJson('/api/conversations/private/nobody_here')
            ->assertNotFound();
    }

    // -------------------------------------------------------- create private

    public function test_create_private_conversation_returns_201_and_adds_both_participants(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAsUser($user)
            ->postJson('/api/conversations/private', [
                'user_id' => $other->id,
                'should_join_now' => false,
            ])
            ->assertCreated()
            ->assertJsonStructure(['data' => self::CONVERSATION_KEYS])
            ->assertJsonMissingPath('message')
            ->assertJsonPath('data.type', 'private')
            ->assertJsonPath('data.userTag', $other->tag)
            ->assertJsonPath('data.title', $other->name)
            ->assertJsonPath('data.hasUnread', false);

        $conversationId = $response->json('data.id');

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseHas('conversations', ['id' => $conversationId, 'conversation_type' => 'private']);
        foreach ([$user, $other] as $participant) {
            $this->assertDatabaseHas('participants', [
                'conversation_id' => $conversationId,
                'user_id' => $participant->id,
                'joined_at' => null,
            ]);
        }
    }

    public function test_create_private_conversation_returns_the_existing_one_on_repeat(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $payload = ['user_id' => $other->id, 'should_join_now' => false];

        $this->actingAsUser($user);
        $first = $this->postJson('/api/conversations/private', $payload)->assertCreated();
        $second = $this->postJson('/api/conversations/private', $payload)->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('participants', 2);
    }

    public function test_create_private_conversation_finds_the_one_started_by_the_other_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $existing = $this->privateConversation($other, $user);

        $this->actingAsUser($user)
            ->postJson('/api/conversations/private', ['user_id' => $other->id, 'should_join_now' => false])
            ->assertCreated()
            ->assertJsonPath('data.id', $existing->id);

        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_new_private_conversation_is_hidden_from_both_sides_until_the_first_message(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson('/api/conversations/private', ['user_id' => $other->id, 'should_join_now' => false])
            ->assertCreated();

        $this->getJson('/api/conversations')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAsUser($other)
            ->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_create_private_conversation_validates_the_recipient(): void
    {
        $this->actingAsUser(User::factory()->create());

        $this->postJson('/api/conversations/private', ['should_join_now' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');

        $this->postJson('/api/conversations/private', [
            'user_id' => '00000000-0000-4000-8000-000000000000',
            'should_join_now' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    public function test_create_private_conversation_rejects_a_malformed_recipient_id(): void
    {
        $this->actingAsUser(User::factory()->create())
            ->postJson('/api/conversations/private', ['user_id' => 'not-a-uuid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    public function test_create_private_conversation_rejects_yourself_as_the_recipient(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson('/api/conversations/private', ['user_id' => $user->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_create_private_conversation_rejects_a_recipient_who_has_not_finished_registration(): void
    {
        $pending = User::factory()->pendingProfile()->create();

        $this->actingAsUser(User::factory()->create())
            ->postJson('/api/conversations/private', ['user_id' => $pending->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    public function test_create_private_conversation_does_not_join_by_default(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson('/api/conversations/private', ['user_id' => $other->id])
            ->assertCreated();

        $this->assertDatabaseHas('participants', ['user_id' => $user->id, 'joined_at' => null]);
    }

    // --------------------------------------------------------------- destroy

    public function test_participant_can_delete_a_conversation_with_everything_in_it(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->privateConversation($user, $other);
        $this->message($conversation, $other);
        $this->message($conversation, $user);

        $this->actingAsUser($user)
            ->deleteJson("/api/conversations/{$conversation->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('participants', ['conversation_id' => $conversation->id]);
        $this->assertDatabaseMissing('messages', ['conversation_id' => $conversation->id]);

        $this->actingAsUser($other)
            ->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_read_state_is_deleted_with_the_conversation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->privateConversation($user, $other);
        $this->message($conversation, $other);

        $this->actingAsUser($user);
        $this->postJson("/api/conversations/{$conversation->id}/mark-as-read")->assertNoContent();
        $this->assertDatabaseCount('participants', 2);

        $this->deleteJson("/api/conversations/{$conversation->id}")->assertNoContent();

        $this->assertDatabaseCount('participants', 0);
    }

    public function test_deleting_a_conversation_removes_its_attachment_files_only(): void
    {
        $user = User::factory()->create();
        $conversation = $this->privateConversation($user, User::factory()->create());
        $other = $this->privateConversation($user, User::factory()->create());

        $this->actingAsUser($user);
        foreach ([$conversation, $other] as $target) {
            $this->post("/api/conversations/{$target->id}/messages", [
                'attachment' => UploadedFile::fake()->image('photo.jpg', 640, 480),
            ], ['Accept' => 'application/json'])->assertCreated();
        }

        $this->deleteJson("/api/conversations/{$conversation->id}")->assertNoContent();

        $this->assertSame([], Storage::disk('s3')->allFiles("attachments/{$conversation->id}"));
        $this->assertCount(2, Storage::disk('s3')->allFiles("attachments/{$other->id}"));
    }

    public function test_non_participant_cannot_delete_a_conversation(): void
    {
        $conversation = $this->privateConversation(User::factory()->create(), User::factory()->create());

        $this->actingAsUser(User::factory()->create())
            ->deleteJson("/api/conversations/{$conversation->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseCount('participants', 2);
    }
}

<?php

namespace Tests\Feature\Storage;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageTest extends TestCase
{
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

    /**
     * Sends the file as a message and returns its storage URLs.
     *
     * @return array{original: string, thumbnail?: string}
     */
    private function sendAttachment(UploadedFile $file): array
    {
        return $this->actingAsUser($this->author)
            ->post("/api/conversations/{$this->conversation->id}/messages", [
                'attachment' => $file,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.message.attachment.urls');
    }

    public function test_participants_can_read_an_attachment(): void
    {
        $urls = $this->sendAttachment(UploadedFile::fake()->image('photo.jpg', 640, 480));

        foreach ([$this->author, $this->recipient] as $user) {
            $this->actingAsUser($user);
            $this->get($urls['original'])->assertOk();
            $this->get($urls['thumbnail'])->assertOk();
        }
    }

    public function test_non_participant_cannot_read_an_attachment(): void
    {
        $urls = $this->sendAttachment(UploadedFile::fake()->image('photo.jpg', 640, 480));

        $this->actingAsUser(User::factory()->create());
        $this->getJson($urls['original'])->assertForbidden();
        $this->getJson($urls['thumbnail'])->assertForbidden();
    }

    public function test_an_attachment_cannot_be_reached_through_another_conversation(): void
    {
        $urls = $this->sendAttachment(UploadedFile::fake()->image('photo.jpg', 640, 480));
        $outsider = User::factory()->create();
        $own = $this->privateConversation($outsider, User::factory()->create());

        $path = str_replace(
            "attachments/{$this->conversation->id}/",
            "attachments/{$own->id}/../{$this->conversation->id}/",
            $urls['original'],
        );

        $this->actingAsUser($outsider)->getJson($path)->assertNotFound();
    }

    public function test_images_are_served_inline_and_never_sniffed(): void
    {
        $urls = $this->sendAttachment(UploadedFile::fake()->image('photo.jpg', 640, 480));

        $this->get($urls['original'])
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Content-Disposition', 'inline')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_other_files_are_served_as_downloads(): void
    {
        $urls = $this->sendAttachment(
            UploadedFile::fake()->createWithContent('page.html', '<html><script>alert(1)</script></html>')
        );

        $response = $this->get($urls['original'])
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringContainsString('alert(1)', $response->streamedContent());
    }

    public function test_avatars_are_readable_by_any_signed_in_user(): void
    {
        Storage::disk('s3')->put("avatars/{$this->author->id}/small.jpg", 'avatar');

        $this->actingAsUser(User::factory()->create())
            ->get("/api/storage/avatars/{$this->author->id}/small.jpg")
            ->assertOk();
    }

    public function test_paths_outside_avatars_and_attachments_do_not_exist(): void
    {
        Storage::disk('s3')->put('backups/dump.sql', 'secret');
        Storage::disk('s3')->put('attachments/not-a-conversation/file.txt', 'secret');

        $this->actingAsUser($this->author);
        $this->getJson('/api/storage/backups/dump.sql')->assertNotFound();
        $this->getJson('/api/storage/attachments/not-a-conversation/file.txt')->assertNotFound();
        $this->getJson('/api/storage/attachments/00000000-0000-4000-8000-000000000000/file.txt')->assertNotFound();
    }

    public function test_a_missing_file_is_not_found(): void
    {
        $this->actingAsUser($this->author)
            ->getJson("/api/storage/attachments/{$this->conversation->id}/missing.jpg")
            ->assertNotFound();
    }

    public function test_storage_requires_authentication(): void
    {
        $this->getJson("/api/storage/avatars/{$this->author->id}/small.jpg")->assertUnauthorized();
    }
}

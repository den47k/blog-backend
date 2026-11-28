<?php

namespace Tests\Feature\Storage;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Storage\StorageUrlGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

class StorageTest extends TestCase
{
    use InteractsWithAuth;

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

    private function signed(string $path): string
    {
        return app(StorageUrlGenerator::class)->url($path);
    }

    public function test_a_signed_attachment_url_works_without_a_bearer_token(): void
    {
        $urls = $this->sendAttachment(UploadedFile::fake()->image('photo.jpg', 640, 480));

        $this->withoutBearer();
        $this->get($urls['original'])->assertOk();
        $this->get($urls['thumbnail'])->assertOk();
    }

    public function test_an_unsigned_url_is_rejected(): void
    {
        Storage::disk('s3')->put("avatars/{$this->author->id}/small.jpg", 'avatar');

        $this->actingAsUser($this->author)
            ->getJson("/api/storage/avatars/{$this->author->id}/small.jpg")
            ->assertForbidden();
    }

    public function test_an_expired_url_is_rejected(): void
    {
        Storage::disk('s3')->put("avatars/{$this->author->id}/small.jpg", 'avatar');
        $url = $this->signed("avatars/{$this->author->id}/small.jpg");

        $this->travel(3)->days();

        $this->getJson($url)->assertForbidden();
    }

    public function test_a_url_whose_path_was_changed_is_rejected(): void
    {
        $urls = $this->sendAttachment(UploadedFile::fake()->image('photo.jpg', 640, 480));
        $own = $this->privateConversation(User::factory()->create(), User::factory()->create());

        $tampered = str_replace(
            "attachments/{$this->conversation->id}/",
            "attachments/{$own->id}/../{$this->conversation->id}/",
            $urls['original'],
        );

        $this->getJson($tampered)->assertForbidden();
    }

    public function test_urls_stay_the_same_within_a_day(): void
    {
        $path = "avatars/{$this->author->id}/small.jpg";
        $first = $this->signed($path);

        $this->travel(1)->hours();

        $this->assertSame($first, $this->signed($path));
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

    public function test_signed_avatar_urls_are_readable(): void
    {
        Storage::disk('s3')->put("avatars/{$this->author->id}/small.jpg", 'avatar');

        $this->get($this->signed("avatars/{$this->author->id}/small.jpg"))->assertOk();
    }

    public function test_paths_outside_avatars_and_attachments_do_not_exist(): void
    {
        Storage::disk('s3')->put('backups/dump.sql', 'secret');
        Storage::disk('s3')->put('attachments/not-a-conversation/file.txt', 'secret');

        $this->getJson($this->signed('backups/dump.sql'))->assertNotFound();
        $this->getJson($this->signed('attachments/not-a-conversation/file.txt'))->assertNotFound();
        $this->getJson($this->signed('attachments/a/../b/file.txt'))->assertNotFound();
    }

    public function test_a_missing_file_is_not_found(): void
    {
        $this->getJson($this->signed("attachments/{$this->conversation->id}/missing.jpg"))
            ->assertNotFound();
    }
}

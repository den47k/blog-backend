<?php

namespace Tests\Feature\User;

use App\Models\User;
use App\Services\Storage\StorageUrlGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    private function uploadAvatar(User $user, UploadedFile $file)
    {
        return $this->post("/api/users/{$user->id}/avatar", ['avatar' => $file], ['Accept' => 'application/json']);
    }

    // ------------------------------------------------------------------ name

    public function test_user_can_rename_themselves(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->actingAsUser($user)
            ->patchJson("/api/users/{$user->id}", ['name' => 'New Name'])
            ->assertOk()
            ->assertExactJson(['name' => 'New Name']);

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_rename_validates_the_name(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        foreach ([[], ['name' => 'ab'], ['name' => str_repeat('a', 33)]] as $payload) {
            $this->patchJson("/api/users/{$user->id}", $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('name');
        }
    }

    public function test_user_cannot_rename_someone_else(): void
    {
        $other = User::factory()->create(['name' => 'Untouched']);

        $this->actingAsUser(User::factory()->create())
            ->patchJson("/api/users/{$other->id}", ['name' => 'Hacked'])
            ->assertForbidden();

        $this->assertSame('Untouched', $other->fresh()->name);
    }

    // ---------------------------------------------------------------- avatar

    public function test_user_can_upload_an_avatar(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user);
        $response = $this->uploadAvatar($user, UploadedFile::fake()->image('me.png', 800, 600))
            ->assertOk()
            ->assertJsonStructure(['avatar' => ['original', 'medium', 'small']]);

        $paths = $user->fresh()->avatar;
        Storage::disk('s3')->assertExists(array_values($paths));

        foreach (['original', 'medium', 'small'] as $size) {
            $this->assertSame(app(StorageUrlGenerator::class)->url($paths[$size]), $response->json("avatar.{$size}"));
        }

        // Square crops, whatever the shape of the upload.
        $this->assertSame([300, 300], array_slice(getimagesizefromstring(Storage::disk('s3')->get($paths['medium'])), 0, 2));
        $this->assertSame([100, 100], array_slice(getimagesizefromstring(Storage::disk('s3')->get($paths['small'])), 0, 2));
    }

    public function test_a_new_avatar_replaces_the_old_files(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        $this->uploadAvatar($user, UploadedFile::fake()->image('first.png', 400, 400))->assertOk();
        $this->uploadAvatar($user, UploadedFile::fake()->image('second.png', 400, 400))->assertOk();

        $this->assertEqualsCanonicalizing(
            array_values($user->fresh()->avatar),
            Storage::disk('s3')->allFiles("avatars/{$user->id}"),
        );
    }

    public function test_a_new_avatar_gets_new_urls(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        $first = $this->uploadAvatar($user, UploadedFile::fake()->image('first.png', 400, 400))->json('avatar');
        $second = $this->uploadAvatar($user, UploadedFile::fake()->image('second.png', 400, 400))->json('avatar');

        foreach (['original', 'medium', 'small'] as $size) {
            $this->assertNotSame($first[$size], $second[$size]);
        }
    }

    public function test_avatar_must_be_an_image(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        $this->uploadAvatar($user, UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        $this->postJson("/api/users/{$user->id}/avatar", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');
    }

    public function test_user_can_delete_their_avatar(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);
        $this->uploadAvatar($user, UploadedFile::fake()->image('me.png', 400, 400))->assertOk();

        $this->deleteJson("/api/users/{$user->id}/avatar")->assertOk();

        $this->assertNull($user->fresh()->avatar);
        $this->assertSame([], Storage::disk('s3')->allFiles("avatars/{$user->id}"));
    }

    public function test_user_cannot_change_someone_elses_avatar(): void
    {
        $other = User::factory()->create();
        $this->actingAsUser(User::factory()->create());

        $this->uploadAvatar($other, UploadedFile::fake()->image('me.png', 400, 400))->assertForbidden();
        $this->deleteJson("/api/users/{$other->id}/avatar")->assertForbidden();

        $this->assertSame([], Storage::disk('s3')->allFiles());
    }
}

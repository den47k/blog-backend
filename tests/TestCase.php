<?php

namespace Tests;

use App\Models\Conversation;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    private const TEST_DATABASE = 'testing';

    private const TEST_REDIS_DB = 15;

    /**
     * Runs before RefreshDatabase gets a chance to migrate anything, so a
     * misconfigured environment can never wipe the development data.
     */
    protected function setUpTraits()
    {
        $this->guardAgainstNonTestingStores();

        return parent::setUpTraits();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->flushdb();

        Http::preventStrayRequests();
        Http::fake([
            $this->centrifugoUrl().'/*' => Http::response(['result' => new \stdClass]),
        ]);

        Storage::fake('s3');
    }

    protected function actingAsUser(User $user): static
    {
        Sanctum::actingAs($user, ['*']);

        return $this;
    }

    /**
     * Guards cache the resolved user for the lifetime of the application
     * instance. Call this between requests that carry different bearer tokens.
     */
    protected function resetAuth(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    protected function privateConversation(User $a, User $b, bool $joined = true): Conversation
    {
        $conversation = Conversation::factory()->create();

        foreach ([$a, $b] as $user) {
            Participant::factory()
                ->for($conversation)
                ->for($user)
                ->create(['joined_at' => $joined ? now() : null]);
        }

        return $conversation;
    }

    /**
     * Decoded bodies of every request sent to Centrifugo's /api/broadcast,
     * in the order they were published: ['channels' => [...], 'data' => ['event' => ..., 'data' => [...]]].
     *
     * @return list<array{channels: list<string>, data: array<string, mixed>}>
     */
    protected function centrifugoPublications(): array
    {
        return Http::recorded(
            fn (Request $request) => $request->url() === $this->centrifugoUrl().'/api/broadcast'
        )
            ->map(fn (array $pair) => $pair[0]->data())
            ->values()
            ->all();
    }

    /**
     * @return list<array{channels: list<string>, data: array<string, mixed>}>
     */
    protected function centrifugoPublicationsOf(string $event): array
    {
        return array_values(array_filter(
            $this->centrifugoPublications(),
            fn (array $publication) => ($publication['data']['event'] ?? null) === $event,
        ));
    }

    private function centrifugoUrl(): string
    {
        return rtrim((string) config('centrifugo.url'), '/');
    }

    private function guardAgainstNonTestingStores(): void
    {
        $database = DB::connection()->getDatabaseName();
        if ($database !== self::TEST_DATABASE) {
            throw new RuntimeException(
                "Refusing to run tests against database [{$database}]; expected [".self::TEST_DATABASE.'].'
            );
        }

        $redisDb = (int) config('database.redis.default.database');
        if ($redisDb !== self::TEST_REDIS_DB) {
            throw new RuntimeException(
                "Refusing to run tests against Redis DB [{$redisDb}]; expected [".self::TEST_REDIS_DB.'].'
            );
        }
    }
}

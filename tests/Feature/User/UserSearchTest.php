<?php

namespace Tests\Feature\User;

use App\Models\User;
use Tests\Concerns\AssertsApiContract;
use Tests\TestCase;

class UserSearchTest extends TestCase
{
    use AssertsApiContract;

    public function test_search_matches_name_and_tag_case_insensitively(): void
    {
        $byName = User::factory()->create(['name' => 'Alice Wonder', 'tag' => 'first_user']);
        $byTag = User::factory()->create(['name' => 'Bob Builder', 'tag' => 'alice_in_chains']);
        User::factory()->create(['name' => 'Carol Danvers', 'tag' => 'third_user']);

        $response = $this->actingAsUser(User::factory()->create(['name' => 'Searcher', 'tag' => 'searcher']))
            ->getJson('/api/users/search?query=ALICE')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => ['*' => self::USER_KEYS]]);

        $this->assertEqualsCanonicalizing([$byName->id, $byTag->id], $response->json('data.*.id'));
    }

    public function test_search_excludes_the_searching_user(): void
    {
        $me = User::factory()->create(['name' => 'Alice Wonder', 'tag' => 'alice_one']);
        $other = User::factory()->create(['name' => 'Alice Cooper', 'tag' => 'alice_two']);

        $this->actingAsUser($me)
            ->getJson('/api/users/search?query=alice')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $other->id)
            ->assertJsonPath('data.0.name', 'Alice Cooper')
            ->assertJsonPath('data.0.tag', 'alice_two')
            ->assertJsonPath('data.0.avatar', null);
    }

    public function test_search_skips_users_who_have_not_finished_registration(): void
    {
        $active = User::factory()->create(['name' => 'Alice Wonder']);
        User::factory()->pendingProfile()->create(['name' => 'Alice Pending']);
        User::factory()->pendingEmail()->create(['name' => 'Alice Unverified']);

        $this->actingAsUser(User::factory()->create(['name' => 'Searcher', 'tag' => 'searcher']))
            ->getJson('/api/users/search?query=alice')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);
    }

    public function test_search_treats_like_wildcards_literally(): void
    {
        $underscore = User::factory()->create(['name' => 'Bob', 'tag' => 'bob_builder']);
        User::factory()->create(['name' => 'Bobby', 'tag' => 'bobXbuilder']);
        $percent = User::factory()->create(['name' => '100% Carol', 'tag' => 'carol_one']);
        User::factory()->create(['name' => '100 Carols', 'tag' => 'carol_two']);

        $this->actingAsUser(User::factory()->create(['name' => 'Searcher', 'tag' => 'searcher']));

        $this->getJson('/api/users/search?query='.urlencode('bob_b'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $underscore->id);

        $this->getJson('/api/users/search?query='.urlencode('0%'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $percent->id);

        $this->getJson('/api/users/search?query='.urlencode('%%'))
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_search_returns_an_empty_list_when_nothing_matches(): void
    {
        User::factory()->create(['name' => 'Alice Wonder', 'tag' => 'alice_one']);

        $this->actingAsUser(User::factory()->create(['name' => 'Searcher', 'tag' => 'searcher']))
            ->getJson('/api/users/search?query=zzzz')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_search_returns_at_most_ten_users(): void
    {
        User::factory()->count(12)->create(['name' => 'Agent Smith']);

        $this->actingAsUser(User::factory()->create(['name' => 'Searcher', 'tag' => 'searcher']))
            ->getJson('/api/users/search?query=smith')
            ->assertOk()
            ->assertJsonCount(10, 'data');
    }

    public function test_search_requires_a_query_of_at_least_two_characters(): void
    {
        $this->actingAsUser(User::factory()->create());

        $this->getJson('/api/users/search?query=a')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('query');

        $this->getJson('/api/users/search')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('query');
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\Device;
use App\Models\User;
use Tests\Concerns\AssertsApiContract;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

class DeviceTest extends TestCase
{
    use AssertsApiContract, InteractsWithAuth;

    /** wisp-app/packages/domain/src/device.ts */
    private const DEVICE_KEYS = [
        'id', 'deviceName', 'clientType', 'platform', 'browser', 'ipAddress',
        'lastSeenAt', 'lastSeenIp', 'isCurrent', 'createdAt',
    ];

    public function test_index_lists_the_users_devices_and_marks_the_current_one(): void
    {
        $user = User::factory()->create();
        $laptop = $this->login($user, 'Laptop');
        $phone = $this->login($user, 'Phone');
        Device::factory()->create();

        $response = $this->withBearer($phone['token'])
            ->getJson('/api/auth/devices')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => ['*' => self::DEVICE_KEYS]]);

        $devices = collect($response->json('data'))->keyBy('id');

        $this->assertIsoDateTime($devices[$phone['device_id']]['createdAt']);

        $this->assertSame('Laptop', $devices[$laptop['device_id']]['deviceName']);
        $this->assertFalse($devices[$laptop['device_id']]['isCurrent']);
        $this->assertSame('Phone', $devices[$phone['device_id']]['deviceName']);
        $this->assertTrue($devices[$phone['device_id']]['isCurrent']);
    }

    public function test_a_device_can_be_renamed(): void
    {
        $user = User::factory()->create();
        $session = $this->login($user, 'Old name');

        $this->withBearer($session['token'])
            ->patchJson("/api/auth/devices/{$session['device_id']}", ['device_name' => 'New name'])
            ->assertOk()
            ->assertJsonStructure(['data' => self::DEVICE_KEYS])
            ->assertJsonPath('data.deviceName', 'New name');

        $this->assertDatabaseHas('devices', ['id' => $session['device_id'], 'device_name' => 'New name']);
    }

    public function test_rename_requires_a_name(): void
    {
        $user = User::factory()->create();
        $session = $this->login($user);

        $this->withBearer($session['token'])
            ->patchJson("/api/auth/devices/{$session['device_id']}", ['device_name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['device_name']);
    }

    public function test_revoking_a_device_signs_it_out(): void
    {
        $user = User::factory()->create();
        $laptop = $this->login($user, 'Laptop');
        $phone = $this->login($user, 'Phone');

        $this->withBearer($laptop['token'])
            ->deleteJson("/api/auth/devices/{$phone['device_id']}")
            ->assertNoContent();

        $this->assertDatabaseMissing('devices', ['id' => $phone['device_id']]);
        $this->assertSame(1, $user->tokens()->count());

        $this->withBearer($phone['token'])->getJson('/api/auth/me')->assertUnauthorized();
        $this->withBearer($laptop['token'])->getJson('/api/auth/me')->assertOk();
    }

    public function test_another_users_device_cannot_be_renamed_or_revoked(): void
    {
        $user = User::factory()->create();
        $foreign = Device::factory()->create(['device_name' => 'Theirs']);

        $this->withBearer($this->tokenFor($user));

        $this->patchJson("/api/auth/devices/{$foreign->id}", ['device_name' => 'Mine now'])->assertNotFound();
        $this->deleteJson("/api/auth/devices/{$foreign->id}")->assertNotFound();

        $this->assertDatabaseHas('devices', ['id' => $foreign->id, 'device_name' => 'Theirs']);
    }

    public function test_a_malformed_device_id_is_not_found(): void
    {
        $this->withBearer($this->login(User::factory()->create())['token']);

        $this->patchJson('/api/auth/devices/123', ['device_name' => 'Nope'])->assertNotFound();
        $this->deleteJson('/api/auth/devices/123')->assertNotFound();
    }

    public function test_devices_require_authentication(): void
    {
        $this->getJson('/api/auth/devices')->assertUnauthorized();
    }
}

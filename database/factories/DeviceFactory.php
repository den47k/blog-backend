<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Creates the device together with the Sanctum token it is bound to
 * (devices.personal_access_token_id is required and unique).
 *
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'personal_access_token_id' => fn (array $attributes) => User::findOrFail($attributes['user_id'])
                ->createToken('factory-device', ['*'])
                ->accessToken
                ->id,
            'device_name' => 'Test device',
            'client_type' => 'web',
            'platform' => 'Linux',
            'browser' => 'Firefox',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0',
            'last_seen_at' => now(),
            'last_seen_ip' => '127.0.0.1',
        ];
    }
}

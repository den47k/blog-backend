<?php

namespace App\Services\Auth;

use App\Events\Auth\DeviceRegistered;
use App\Events\Auth\DeviceRevoked;
use App\Models\Device;
use App\Models\User;
use App\Repositories\Interfaces\DeviceRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceService
{
    public const TOUCH_DEBOUNCE_MINUTES = 5;

    public function __construct(
        private readonly DeviceRepositoryInterface $deviceRepository,
    ) {}

    public function issueForUser(User $user, string $deviceName, Request $request): array
    {
        $result = DB::transaction(function () use ($user, $deviceName, $request) {
            $newToken = $user->createToken($deviceName, ['*']);

            $ua = $this->parseUserAgent($request->userAgent());

            $device = $this->deviceRepository->create([
                'user_id' => $user->id,
                'personal_access_token_id' => $newToken->accessToken->id,
                'device_name' => $deviceName,
                'client_type' => $ua['client_type'],
                'platform' => $ua['platform'],
                'browser' => $ua['browser'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'last_seen_at' => now(),
                'last_seen_ip' => $request->ip(),
            ]);

            return [
                'token' => $newToken->plainTextToken,
                'device' => $device,
            ];
        });

        event(new DeviceRegistered($user, [
            'device_id' => $result['device']->id,
            'device_name' => $result['device']->device_name,
            'client_type' => $result['device']->client_type,
            'platform' => $result['device']->platform,
            'browser' => $result['device']->browser,
            'ip' => $request->ip(),
        ]));

        return $result;
    }

    public function listForUser(User $user): Collection
    {
        return $this->deviceRepository->listForUser($user);
    }

    public function rename(User $user, string $deviceId, string $newName): Device
    {
        $device = $this->deviceRepository->findForUser($user, $deviceId);

        if (! $device) {
            throw (new ModelNotFoundException)->setModel(Device::class, [$deviceId]);
        }

        return $this->deviceRepository->update($device, ['device_name' => $newName]);
    }

    public function revoke(User $user, string $deviceId): void
    {
        $device = $this->deviceRepository->findForUser($user, $deviceId);

        if (! $device) {
            throw (new ModelNotFoundException)->setModel(Device::class, [$deviceId]);
        }

        PersonalAccessToken::where('id', $device->personal_access_token_id)->delete();

        event(new DeviceRevoked($user, [
            'device_id' => $device->id,
            'device_name' => $device->device_name,
        ]));
    }

    public function touch(int $personalAccessTokenId, ?string $ip): void
    {
        $device = $this->deviceRepository->forToken($personalAccessTokenId);

        if (! $device) {
            return;
        }

        if ($device->last_seen_at && $device->last_seen_at->gt(now()->subMinutes(self::TOUCH_DEBOUNCE_MINUTES))) {
            return;
        }

        $this->deviceRepository->update($device, [
            'last_seen_at' => now(),
            'last_seen_ip' => $ip,
        ]);
    }

    private function parseUserAgent(?string $ua): array
    {
        if (! $ua) {
            return ['client_type' => 'unknown', 'platform' => null, 'browser' => null];
        }

        $platform = $this->detectPlatform($ua);
        $browser = $this->detectBrowser($ua);
        $clientType = $this->detectClientType($ua, $platform);

        return [
            'client_type' => $clientType,
            'platform' => $platform,
            'browser' => $browser,
        ];
    }

    private function detectPlatform(string $ua): ?string
    {
        return match (true) {
            (bool) preg_match('/iPhone|iPad|iPod/i', $ua) => 'iOS',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/Macintosh|Mac OS X/i', $ua) => 'macOS',
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/Linux/i', $ua) => 'Linux',
            default => null,
        };
    }

    private function detectBrowser(string $ua): ?string
    {
        return match (true) {
            (bool) preg_match('/Edg\//i', $ua) => 'Edge',
            (bool) preg_match('/Firefox/i', $ua) => 'Firefox',
            (bool) preg_match('/OPR\/|Opera/i', $ua) => 'Opera',
            (bool) preg_match('/Chrome/i', $ua) => 'Chrome',
            (bool) preg_match('/Safari/i', $ua) => 'Safari',
            (bool) preg_match('/curl/i', $ua) => 'curl',
            default => null,
        };
    }

    private function detectClientType(string $ua, ?string $platform): string
    {
        if (preg_match('/Electron|Tauri/i', $ua)) {
            return 'desktop';
        }

        if (in_array($platform, ['Android', 'iOS'], true)) {
            return 'mobile';
        }

        if (in_array($platform, ['macOS', 'Windows', 'Linux'], true)) {
            return 'web';
        }

        return 'unknown';
    }
}

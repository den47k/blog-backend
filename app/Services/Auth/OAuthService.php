<?php

namespace App\Services\Auth;

use App\Enums\OtpPurpose;
use App\Enums\TokenAbility;
use App\Enums\UserStatus;
use App\Events\Auth\OAuthIdentityLinked;
use App\Events\Auth\OAuthIdentityUnlinked;
use App\Models\OAuthIdentity;
use App\Models\User;
use App\Notifications\EmailOtpNotification;
use App\Repositories\Interfaces\OAuthIdentityRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class OAuthService
{
    public const ALLOWED_PROVIDERS = ['google', 'github'];

    public const STATE_TTL_SECONDS = 600;

    public const LINK_TTL_MINUTES = 10;

    public const REGISTER_TTL_MINUTES = 30;

    public function __construct(
        private readonly OAuthIdentityRepositoryInterface $identityRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly OtpService $otpService,
        private readonly TokenService $tokenService,
    ) {}

    public function redirectUrl(string $provider): array
    {
        $this->assertProvider($provider);

        $nonce = Str::random(40);
        Cache::put("oauth:state:{$nonce}", [
            'provider' => $provider,
            'issued_at' => time(),
        ], self::STATE_TTL_SECONDS);

        $driver = Socialite::driver($provider)->stateless();
        if ($provider === 'github') {
            $driver = $driver->scopes(['user:email']);
        }

        $url = $driver->with(['state' => $nonce])->redirect()->getTargetUrl();

        return ['url' => $url];
    }

    public function handleCallback(string $provider, Request $request): array
    {
        $this->assertProvider($provider);

        $state = $request->query('state');
        if (! $state) {
            throw new BadRequestHttpException('Missing OAuth state.');
        }

        $cached = Cache::pull("oauth:state:{$state}");
        if (! $cached || $cached['provider'] !== $provider) {
            throw new BadRequestHttpException('Invalid or expired OAuth state.');
        }

        if ($cached['issued_at'] < time() - self::STATE_TTL_SECONDS) {
            throw new BadRequestHttpException('OAuth state expired.');
        }

        $socialUser = Socialite::driver($provider)->stateless()->user();
        $providerUid = (string) $socialUser->getId();
        $providerEmail = $socialUser->getEmail();
        $pVerified = $this->extractEmailVerified($provider, $socialUser);

        $identity = $this->identityRepository->findByProviderUid($provider, $providerUid);
        if ($identity) {
            return ['kind' => 'logged_in', 'user' => $identity->user];
        }

        $localUser = $providerEmail
            ? $this->userRepository->findByEmailAnyStatus($providerEmail)
            : null;

        if ($localUser) {
            if ($localUser->email_verified_at && $pVerified) {
                $this->createIdentity($localUser, $provider, $providerUid, $providerEmail, $pVerified, $socialUser);

                return ['kind' => 'logged_in', 'user' => $localUser];
            }

            Cache::put(
                "oauth:link:{$localUser->id}:{$provider}",
                [
                    'provider' => $provider,
                    'provider_user_id' => $providerUid,
                    'provider_email' => $providerEmail,
                    'provider_email_verified' => $pVerified,
                    'data' => (array) ($socialUser->user ?? []),
                ],
                self::STATE_TTL_SECONDS,
            );

            $linkToken = $this->tokenService->issueToken(
                $localUser,
                "oauth-link:{$provider}",
                [TokenAbility::OAuthLinkPending->value],
                Carbon::now()->addMinutes(self::LINK_TTL_MINUTES),
            );

            return [
                'kind' => 'link_required',
                'link_token' => $linkToken,
                'expires_in' => self::LINK_TTL_MINUTES * 60,
            ];
        }

        if ($pVerified) {
            $newUser = DB::transaction(function () use ($providerEmail, $socialUser, $provider, $providerUid, $pVerified) {
                $user = $this->userRepository->create([
                    'email' => $providerEmail,
                    'email_verified_at' => now(),
                    'name' => $socialUser->getName() ?: null,
                    'tag' => null,
                    'password' => Hash::make(Str::random(64)),
                    'status' => UserStatus::PendingProfile,
                ]);
                $this->createIdentity($user, $provider, $providerUid, $providerEmail, $pVerified, $socialUser);

                return $user;
            });

            $regToken = $this->tokenService->issueToken(
                $newUser,
                'registration',
                [TokenAbility::RegisterPending->value],
                Carbon::now()->addMinutes(self::REGISTER_TTL_MINUTES),
            );

            return [
                'kind' => 'pending_profile',
                'token' => $regToken,
                'expires_in' => self::REGISTER_TTL_MINUTES * 60,
            ];
        }

        $newUser = DB::transaction(function () use ($providerEmail, $socialUser, $provider, $providerUid, $pVerified) {
            $user = $this->userRepository->create([
                'email' => $providerEmail,
                'email_verified_at' => null,
                'name' => $socialUser->getName() ?: null,
                'tag' => null,
                'password' => Hash::make(Str::random(64)),
                'status' => UserStatus::PendingEmail,
            ]);
            $this->createIdentity($user, $provider, $providerUid, $providerEmail, $pVerified, $socialUser);

            return $user;
        });

        $code = $this->otpService->issue($providerEmail, OtpPurpose::Register, $request->ip());
        $newUser->notify(new EmailOtpNotification($code, OtpPurpose::Register->value));

        $regToken = $this->tokenService->issueToken(
            $newUser,
            'registration',
            [TokenAbility::RegisterPending->value],
            Carbon::now()->addMinutes(self::REGISTER_TTL_MINUTES),
        );

        return [
            'kind' => 'pending_email',
            'token' => $regToken,
            'expires_in' => self::REGISTER_TTL_MINUTES * 60,
        ];
    }

    public function confirmLink(User $user, string $provider, string $password, Request $request): array
    {
        $this->assertProvider($provider);

        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Invalid password.'],
            ]);
        }

        $cached = Cache::pull("oauth:link:{$user->id}:{$provider}");
        if (! $cached || $cached['provider'] !== $provider) {
            throw ValidationException::withMessages([
                'oauth' => ['No pending link request for this provider.'],
            ]);
        }

        $linkToken = $user->currentAccessToken();

        $user = DB::transaction(function () use ($user, $cached) {
            $this->identityRepository->create([
                'user_id' => $user->id,
                'provider' => $cached['provider'],
                'provider_user_id' => $cached['provider_user_id'],
                'provider_email' => $cached['provider_email'],
                'provider_email_verified' => $cached['provider_email_verified'],
                'data' => $cached['data'],
                'linked_at' => now(),
            ]);

            if ($cached['provider_email_verified'] && ! $user->email_verified_at) {
                $user = $this->userRepository->markEmailVerified($user);
            }

            if ($user->email_verified_at && $user->status === UserStatus::PendingEmail) {
                $user = $this->userRepository->update($user, ['status' => UserStatus::PendingProfile]);
            }

            return $user;
        });

        $linkToken?->delete();

        event(new OAuthIdentityLinked($user, [
            'provider' => $cached['provider'],
            'provider_user_id' => $cached['provider_user_id'],
            'provider_email' => $cached['provider_email'],
        ]));

        if ($user->status === UserStatus::Active) {
            return ['kind' => 'logged_in', 'user' => $user];
        }

        if ($user->status === UserStatus::PendingEmail) {
            $code = $this->otpService->issue($user->email, OtpPurpose::Register, $request->ip());
            $user->notify(new EmailOtpNotification($code, OtpPurpose::Register->value));

            $regToken = $this->tokenService->issueToken(
                $user,
                'registration',
                [TokenAbility::RegisterPending->value],
                Carbon::now()->addMinutes(self::REGISTER_TTL_MINUTES),
            );

            return [
                'kind' => 'pending_email',
                'user' => $user,
                'token' => $regToken,
                'expires_in' => self::REGISTER_TTL_MINUTES * 60,
            ];
        }

        $regToken = $this->tokenService->issueToken(
            $user,
            'registration',
            [TokenAbility::RegisterPending->value],
            Carbon::now()->addMinutes(self::REGISTER_TTL_MINUTES),
        );

        return [
            'kind' => 'pending_profile',
            'user' => $user,
            'token' => $regToken,
            'expires_in' => self::REGISTER_TTL_MINUTES * 60,
        ];
    }

    public function unlink(User $user, string $identityId): void
    {
        $identity = OAuthIdentity::where('user_id', $user->id)
            ->where('id', $identityId)
            ->first();

        if (! $identity) {
            throw (new ModelNotFoundException)->setModel(OAuthIdentity::class, [$identityId]);
        }

        $remaining = OAuthIdentity::where('user_id', $user->id)
            ->where('id', '!=', $identityId)
            ->count();

        if ($remaining === 0 && ! $user->password_changed_at) {
            throw ValidationException::withMessages([
                'identity' => ['Cannot unlink last auth method without setting a password first.'],
            ]);
        }

        $provider = $identity->provider;
        $providerUid = $identity->provider_user_id;

        $this->identityRepository->delete($identity);

        event(new OAuthIdentityUnlinked($user, [
            'provider' => $provider,
            'provider_user_id' => $providerUid,
        ]));
    }

    private function createIdentity(
        User $user,
        string $provider,
        string $providerUid,
        ?string $providerEmail,
        bool $pVerified,
        SocialiteUser $socialUser,
    ): void {
        $this->identityRepository->create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_user_id' => $providerUid,
            'provider_email' => $providerEmail,
            'provider_email_verified' => $pVerified,
            'data' => (array) ($socialUser->user ?? []),
            'linked_at' => now(),
        ]);

        event(new OAuthIdentityLinked($user, [
            'provider' => $provider,
            'provider_user_id' => $providerUid,
            'provider_email' => $providerEmail,
        ]));
    }

    private function extractEmailVerified(string $provider, SocialiteUser $socialUser): bool
    {
        $raw = $socialUser->user ?? [];

        return match ($provider) {
            'google' => (bool) ($raw['email_verified'] ?? $raw['verified_email'] ?? false),
            'github' => true,
            default => false,
        };
    }

    private function assertProvider(string $provider): void
    {
        if (! in_array($provider, self::ALLOWED_PROVIDERS, true)) {
            throw new BadRequestHttpException("Unsupported OAuth provider: {$provider}");
        }
    }
}

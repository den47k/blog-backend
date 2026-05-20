<?php

namespace App\Providers;

use App\Events\Auth\DeviceRegistered;
use App\Events\Auth\DeviceRevoked;
use App\Events\Auth\OAuthIdentityLinked;
use App\Events\Auth\OAuthIdentityUnlinked;
use App\Events\Auth\TwoFactorDisabled;
use App\Events\Auth\TwoFactorEnabled;
use App\Listeners\Auth\LogAuditEvent;
use App\Listeners\Auth\RevokeTokensOnPasswordReset;
use App\Listeners\Auth\TouchDeviceOnAuthentication;
use App\Models\Conversation;
use App\Models\Message;
use App\Policies\ConversationPolicy;
use App\Policies\MessagePolicy;
use App\Repositories\Caching\CachingConversationRepository;
use App\Repositories\Caching\CachingMessageRepository;
use App\Repositories\Caching\CachingUserRepository;
use App\Repositories\Eloquent\ConversationRepository;
use App\Repositories\Eloquent\DeviceRepository;
use App\Repositories\Eloquent\MessageRepository;
use App\Repositories\Eloquent\OAuthIdentityRepository;
use App\Repositories\Eloquent\ParticipantRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Interfaces\ConversationReadRepositoryInterface;
use App\Repositories\Interfaces\ConversationRepositoryInterface;
use App\Repositories\Interfaces\DeviceRepositoryInterface;
use App\Repositories\Interfaces\MessageRepositoryInterface;
use App\Repositories\Interfaces\OAuthIdentityRepositoryInterface;
use App\Repositories\Interfaces\ParticipantRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\Redis\ConversationReadRepository;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Events\TokenAuthenticated;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        $this->app->bind(UserRepositoryInterface::class, fn($app) => new CachingUserRepository(
            $app->make(UserRepository::class),
        ));
        $this->app->bind(ConversationRepositoryInterface::class, fn($app) => new CachingConversationRepository(
            $app->make(ConversationRepository::class),
        ));
        $this->app->bind(MessageRepositoryInterface::class, fn($app) => new CachingMessageRepository(
            $app->make(MessageRepository::class),
        ));
        $this->app->bind(ParticipantRepositoryInterface::class, ParticipantRepository::class);
        $this->app->bind(ConversationReadRepositoryInterface::class, ConversationReadRepository::class);
        $this->app->bind(DeviceRepositoryInterface::class, DeviceRepository::class);
        $this->app->bind(OAuthIdentityRepositoryInterface::class, OAuthIdentityRepository::class);
    }

    public function boot(): void
    {
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);

        Event::listen(TokenAuthenticated::class, TouchDeviceOnAuthentication::class);
        Event::listen(PasswordReset::class, RevokeTokensOnPasswordReset::class);

        foreach ([
            DeviceRegistered::class,
            DeviceRevoked::class,
            TwoFactorEnabled::class,
            TwoFactorDisabled::class,
            OAuthIdentityLinked::class,
            OAuthIdentityUnlinked::class,
        ] as $eventClass) {
            Event::listen($eventClass, LogAuditEvent::class);
        }

        $this->configureRateLimiters();

        ResetPassword::createUrlUsing(fn($user, string $token) =>
            rtrim(config('app.frontend_url'), '/')
            . '/reset-password?token=' . $token
            . '&email=' . urlencode($user->getEmailForPasswordReset()));
    }

    private function configureRateLimiters(): void
    {
        RateLimiter::for('auth-burst', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return Limit::perMinute(5)->by($request->ip() . '|' . $email);
        });

        RateLimiter::for('auth-otp', function (Request $request) {
            $key = $request->user()?->email ?? $request->ip();

            return Limit::perHour(10)->by('otp:' . $key);
        });

        RateLimiter::for('auth-2fa-verify', function (Request $request) {
            $tokenId = $request->user()?->currentAccessToken()?->id ?? $request->ip();

            return Limit::perMinutes(15, 10)->by('2fa:' . $tokenId);
        });
    }
}

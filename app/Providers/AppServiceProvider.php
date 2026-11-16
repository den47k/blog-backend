<?php

namespace App\Providers;

use App\Events\Auth\DeviceRegistered;
use App\Events\Auth\DeviceRevoked;
use App\Events\Auth\OAuthIdentityLinked;
use App\Events\Auth\OAuthIdentityUnlinked;
use App\Events\Auth\TwoFactorDisabled;
use App\Events\Auth\TwoFactorEnabled;
use App\Listeners\Auth\LogAuditEvent;
use App\Models\Conversation;
use App\Models\Message;
use App\Policies\ConversationPolicy;
use App\Policies\MessagePolicy;
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
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(ConversationRepositoryInterface::class, ConversationRepository::class);
        $this->app->bind(MessageRepositoryInterface::class, MessageRepository::class);
        $this->app->bind(ParticipantRepositoryInterface::class, ParticipantRepository::class);
        $this->app->bind(ConversationReadRepositoryInterface::class, ConversationReadRepository::class);
        $this->app->bind(DeviceRepositoryInterface::class, DeviceRepository::class);
        $this->app->bind(OAuthIdentityRepositoryInterface::class, OAuthIdentityRepository::class);
    }

    public function boot(): void
    {
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);

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

        RateLimiter::for('auth-oauth', fn (Request $request) => Limit::perMinute(20)->by('oauth:' . $request->ip()));

        RateLimiter::for('auth-password-confirm', fn (Request $request) => Limit::perMinute(5)
            ->by('password-confirm:' . ($request->user()?->id ?? $request->ip())));

        RateLimiter::for('messages', fn (Request $request) => Limit::perMinute(60)
            ->by('messages:' . ($request->user()?->id ?? $request->ip())));

        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(30)
            ->by('search:' . ($request->user()?->id ?? $request->ip())));
    }
}

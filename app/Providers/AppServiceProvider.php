<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Models\Message;
use App\Policies\ConversationPolicy;
use App\Policies\MessagePolicy;
use App\Repositories\Redis\ConversationReadRepository;
use App\Repositories\Eloquent\ConversationRepository;
use App\Repositories\Eloquent\MessageRepository;
use App\Repositories\Eloquent\ParticipantRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Interfaces\ConversationReadRepositoryInterface;
use App\Repositories\Interfaces\ConversationRepositoryInterface;
use App\Repositories\Interfaces\MessageRepositoryInterface;
use App\Repositories\Interfaces\ParticipantRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(ConversationRepositoryInterface::class, ConversationRepository::class);
        $this->app->bind(ParticipantRepositoryInterface::class, ParticipantRepository::class);
        $this->app->bind(MessageRepositoryInterface::class, MessageRepository::class);
        $this->app->bind(ConversationReadRepositoryInterface::class, ConversationReadRepository::class);
    }

    public function boot(): void
    {
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);
    }
}

<?php

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'name',
        'tag',
        'email',
        'email_verified_at',
        'password',
        'status',
        'password_changed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
            'avatar' => 'array',
            'status' => UserStatus::class,
        ];
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'participants')->withTimestamps();
    }

    public function activeConversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'participants')
            ->withPivot('joined_at', 'role')
            ->wherePivotNotNull('joined_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function oauthIdentities(): HasMany
    {
        return $this->hasMany(OAuthIdentity::class);
    }

    public function twoFactorSecret(): HasOne
    {
        return $this->hasOne(TwoFactorSecret::class);
    }

    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(RecoveryCode::class);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->twoFactorSecret?->confirmed_at !== null;
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->status === UserStatus::Active;
    }
}

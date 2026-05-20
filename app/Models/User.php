<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Traits\HasProfilePhoto;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasProfilePhoto, HasUuids, Notifiable;

    protected $fillable = [
        'name',
        'tag',
        'email',
        'email_verified_at',
        'password',
        'phone',
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

    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'participants')->withTimestamps();
    }

    public function activeConversations()
    {
        return $this->belongsToMany(Conversation::class, 'participants')
            ->withPivot('joined_at', 'role')
            ->wherePivotNotNull('joined_at');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function oauthIdentities()
    {
        return $this->hasMany(OAuthIdentity::class);
    }

    public function twoFactorSecret()
    {
        return $this->hasOne(TwoFactorSecret::class);
    }

    public function recoveryCodes()
    {
        return $this->hasMany(RecoveryCode::class);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->twoFactorSecret?->confirmed_at !== null;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->status === UserStatus::Active;
    }
}

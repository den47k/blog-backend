<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Conversation;
use App\Models\Message;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasUuids, HasFactory, Notifiable;

    // Model attribute configuration
    protected $fillable = [
        'name',
        'tag',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relationships
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
}

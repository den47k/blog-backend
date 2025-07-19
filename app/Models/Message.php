<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Redis;

class Message extends Model
{
    use HasUuids;

    // Model attribute configuration
    protected $fillable = [
        'content',
        'conversation_id',
        'user_id',
        'parent_id',
        'is_pinned',
        'edited_at'
    ];

    protected $casts = [
        'edited_at' => 'datetime',
        'is_pinned' => 'boolean'
    ];

    // Relationships
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(Message::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Message::class, 'parent_id');
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(MessageUser::class)
            ->withPivot('status', 'read_at');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\User;
use App\Models\Participant;
use App\Models\Message;

class Conversation extends Model
{
    use HasUuids;


    // Model attribute configuration
    protected $fillable = [
        'conversation_type',
        'title',
        'description',
        'creator_id',
        'is_public',
        'last_message_id'
    ];


    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function participants()
    {
        return $this->hasMany(Participant::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage()
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }
}

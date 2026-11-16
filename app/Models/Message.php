<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Message extends Model
{
    use HasFactory, HasUuids;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'content',
        'conversation_id',
        'user_id',
    ];

    /**
     * Cursor pagination casts the timestamp to a string to build the cursor;
     * Carbon's default string form has no microseconds, which would skip
     * messages sent in the same second as the last one on a page.
     */
    protected function asDateTime($value)
    {
        return parent::asDateTime($value)->settings(['toStringFormat' => $this->getDateFormat()]);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachment(): HasOne
    {
        return $this->hasOne(MessageAttachment::class);
    }
}

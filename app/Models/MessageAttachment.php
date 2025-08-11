<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'file_data',
    ];

    protected $casts = [
        'file_data' => 'array',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }


    protected function urls(): Attribute
    {
        return Attribute::make(
            get: function () {
                $urls = [];
                $fileData = $this->file_data;

                if (!empty($fileData['original'])) {
                    $urls['original'] = route('api.storage', ['path' => $fileData['original']]);
                }
                if (!empty($fileData['thumbnail'])) {
                    $urls['thumbnail'] = route('api.storage', ['path' => $fileData['thumbnail']]);
                }
                return $urls;
            }
        );
    }
}

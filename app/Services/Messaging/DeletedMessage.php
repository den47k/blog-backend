<?php

namespace App\Services\Messaging;

use App\Models\Message;

final readonly class DeletedMessage
{
    public function __construct(
        public string $id,
        public bool $wasLastMessage,
        public ?Message $newLastMessage,
    ) {}
}

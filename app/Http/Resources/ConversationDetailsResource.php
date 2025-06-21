<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->conversation_type,
            'participants' => $this->participants->map(function ($participant) {
                return [
                    'id' => $participant->id,
                    'user' => [
                        'id' => $participant->user->id,
                        'name' => $participant->user->name,
                        'tag' => $participant->user->tag,
                    ],
                    'joined_at' => $participant->joined_at,
                    'role' => $participant->role,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

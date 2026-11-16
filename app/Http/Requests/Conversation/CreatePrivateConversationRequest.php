<?php

namespace App\Http\Requests\Conversation;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePrivateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'uuid',
                Rule::notIn([$this->user()->id]),
                Rule::exists('users', 'id')->where('status', UserStatus::Active->value),
            ],
            'should_join_now' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.not_in' => 'You cannot start a conversation with yourself.',
        ];
    }
}

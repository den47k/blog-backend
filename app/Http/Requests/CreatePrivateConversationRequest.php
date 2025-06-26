<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePrivateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'string', 'exists:users,id'],
            'should_join_now' => ['nullable', 'boolean']
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_tag.exists' => 'User with this tag does not exist',
        ];
    }
}

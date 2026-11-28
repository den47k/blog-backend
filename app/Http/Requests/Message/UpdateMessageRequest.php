<?php

namespace App\Http\Requests\Message;

use App\Models\Message;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => [
                'nullable',
                Rule::requiredIf(function () {
                    $message = $this->route('message');

                    return ! ($message instanceof Message && $message->attachment()->exists());
                }),
                'string',
                'max:5000',
            ],
        ];
    }
}

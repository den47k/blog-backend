<?php

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['nullable', 'required_without:attachment', 'string', 'max:5000'],
            'attachment' => ['required_without:content', 'file', 'max:25600'],
        ];
    }
}

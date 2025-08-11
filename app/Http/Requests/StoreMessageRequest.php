<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required_without:attachment', 'string', 'max:5000', 'prohibits:attachment'],
            'attachment' => ['required_without:content', 'file', 'max:25600', 'prohibits:content'],
        ];
    }
}

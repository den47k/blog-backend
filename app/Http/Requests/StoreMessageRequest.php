<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;

        // ToDo
        // $conversation = $this->route('conversation');
        // Gate::allows('view', $conversation);
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:5000']
        ];
    }
}

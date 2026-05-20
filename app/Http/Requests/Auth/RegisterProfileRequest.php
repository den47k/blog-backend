<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:32'],
            'tag' => ['required', 'string', 'min:5', 'max:32', 'regex:/^[a-zA-Z][a-zA-Z0-9_]{4,31}$/', 'unique:users,tag'],
            'device_name' => ['required', 'string', 'max:128'],
        ];
    }
}

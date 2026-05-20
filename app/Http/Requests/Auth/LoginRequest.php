<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:128'],
        ];
    }

    public function credentials(): array
    {
        return $this->only('email', 'password');
    }

    public function deviceName(): string
    {
        return $this->input('device_name', 'web');
    }
}

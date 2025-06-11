<?php

namespace App\Http\Requests;

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
            'password' => 'required',
            'remember' => 'boolean'
        ];
    }

    public function credentials(): array
    {
        return $this->only('email', 'password');
    }

    public function remember(): bool
    {
        return $this->boolean('remember');
    }
}

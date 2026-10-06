<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class PasswordUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:255', 'current_password'],
            'password' => [
                'required',
                'string',
                'max:255',
                'confirmed',
                Password::min(10)->mixedCase()->numbers()->symbols(),
            ],
        ];
    }
}

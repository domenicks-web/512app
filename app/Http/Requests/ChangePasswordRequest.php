<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::default()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Digite sua senha atual.',
            'current_password.current_password' => 'Senha atual incorreta.',
            'password.required' => 'Digite a senha nova.',
            'password.different' => 'A senha nova precisa ser diferente da atual.',
            'password.confirmed' => 'As duas senhas novas não batem.',
        ];
    }

    public function newPassword(): string
    {
        return $this->string('password')->toString();
    }
}

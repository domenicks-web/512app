<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestEmailChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * O próprio email atual também cai no `unique` (é dele, mas não é troca).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
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
            'email.required' => 'Digite o email novo.',
            'email.email' => 'Esse email não parece certo.',
            'email.lowercase' => 'Use o email em letras minúsculas.',
            'email.unique' => 'Esse email já está em uso.',
        ];
    }

    public function newEmail(): string
    {
        return $this->string('email')->trim()->toString();
    }
}

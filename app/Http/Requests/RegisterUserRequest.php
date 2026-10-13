<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Data\RegisterUserData;
use App\Http\Controllers\Auth\ValidateInviteController;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * O convite vem da sessão (validado na etapa anterior), nunca do corpo do
     * formulário.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'invite_code' => $this->session()->get(ValidateInviteController::SESSION_KEY),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::default()],
            'invite_code' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invite_code.required' => 'Valide seu convite antes de criar a conta.',
        ];
    }

    public function toRegisterUserData(): RegisterUserData
    {
        return new RegisterUserData(
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
            inviteCode: $this->string('invite_code')->toString(),
        );
    }
}

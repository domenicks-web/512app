<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Data\RegisterUserData;
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::default()],
            'invite_code' => ['required', 'string', 'exists:invites,code'],
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

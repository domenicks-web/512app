<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginUserRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Email ou senha incorretos.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        // Senha certa, mas suspenso, banido ou conta do sistema: não entra.
        /** @var User $user */
        $user = Auth::user();
        $message = $user->is_system ? 'Essa conta é do sistema.' : $user->activeSanction()?->message();

        if ($message !== null) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages(['email' => $message]);
        }
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Muitas tentativas. Tente de novo em {$seconds} segundos.",
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate($this->string('email')->lower()->toString().'|'.$this->ip());
    }
}

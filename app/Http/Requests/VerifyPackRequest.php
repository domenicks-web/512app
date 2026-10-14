<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VerifyPackRequest extends FormRequest
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
            'server_seed' => ['required', 'string', 'max:255'],
            'server_seed_hash' => ['nullable', 'string', 'regex:/^[0-9a-fA-F]{64}$/'],
            'client_seed' => ['required', 'string', 'max:255', 'regex:/^\S+$/'],
            'nonce' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'server_seed.required' => 'Cole a server seed revelada.',
            'server_seed_hash.regex' => 'O hash tem 64 caracteres (0–9 e a–f).',
            'client_seed.required' => 'Cole a client seed.',
            'client_seed.max' => 'A client seed vai até :max caracteres.',
            'client_seed.regex' => 'A client seed não tem espaço.',
            'nonce.required' => 'Falta o nonce.',
            'nonce.integer' => 'O nonce é um número inteiro.',
            'nonce.min' => 'O nonce não é negativo.',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClientSeedRequest extends FormRequest
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
            'client_seed' => ['required', 'string', 'max:'.config('game.pack.client_seed_max_length'), 'regex:/^\S+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_seed.required' => 'A client seed não pode ficar vazia.',
            'client_seed.max' => 'A client seed vai até :max caracteres.',
            'client_seed.regex' => 'A client seed não pode ter espaço.',
        ];
    }
}

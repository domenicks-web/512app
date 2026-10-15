<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Suspender: um dos prazos do config e o motivo (vai no email). */
class SuspendPlayerRequest extends FormRequest
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
            'days' => ['required', 'integer', Rule::in((array) config('game.sanctions.suspension_days'))],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'days.required' => 'Escolha o prazo.',
            'days.in' => 'Prazo inválido.',
            'reason.required' => 'Escreva o motivo.',
            'reason.min' => 'Escreva um motivo com pelo menos 5 letras.',
        ];
    }
}

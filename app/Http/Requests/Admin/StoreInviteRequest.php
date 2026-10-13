<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Actions\Data\CreateInviteData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Campo vazio = sem limite. `max_uses` vazio é convite infinito,
     * `expires_in_days` vazio é convite que nunca expira.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:'.config('game.invites.max_uses_limit')],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:'.config('game.invites.expires_in_days_limit')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'max_uses.integer' => 'O limite de usos precisa ser um número inteiro.',
            'max_uses.min' => 'O limite de usos precisa ser pelo menos :min.',
            'max_uses.max' => 'O limite de usos pode ser no máximo :max.',
            'expires_in_days.integer' => 'A validade precisa ser um número inteiro de dias.',
            'expires_in_days.min' => 'A validade precisa ser de pelo menos :min dia.',
            'expires_in_days.max' => 'A validade pode ser de no máximo :max dias.',
        ];
    }

    public function toCreateInviteData(): CreateInviteData
    {
        return new CreateInviteData(
            maxUses: $this->filled('max_uses') ? $this->integer('max_uses') : null,
            expiresAt: $this->filled('expires_in_days') ? now()->addDays($this->integer('expires_in_days')) : null,
        );
    }
}

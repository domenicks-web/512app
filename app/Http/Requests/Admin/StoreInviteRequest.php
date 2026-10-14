<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Actions\Data\CreateInviteData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInviteRequest extends FormRequest
{
    /** Onde fica o sufixo da prévia, sorteado em `GET /admin/convites/sufixo`. */
    public const SUFFIX_SESSION_KEY = 'admin.invite_suffix';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['prefix' => mb_strtoupper(trim((string) $this->input('prefix')))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxLength = config('game.invites.code_prefix_max_length');

        return [
            'prefix' => ['required', 'string', "regex:/^[A-Z0-9]{1,{$maxLength}}$/"],
            'max_uses' => ['required', 'integer', 'between:'.config('game.invites.max_uses.min').','.config('game.invites.max_uses.max')],
            'validity' => ['required', 'string', Rule::in(array_map('strval', array_keys(config('game.invites.validity_days'))))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prefix.required' => 'Digite um prefixo.',
            'prefix.regex' => 'O prefixo aceita só letras e números, até '.config('game.invites.code_prefix_max_length').' caracteres.',
            'max_uses.between' => 'O convite vale de :min a :max usos.',
            'validity.in' => 'Escolha 7 dias, 30 dias ou sem validade.',
        ];
    }

    public function toCreateInviteData(): CreateInviteData
    {
        $days = config('game.invites.validity_days')[$this->string('validity')->toString()] ?? null;
        $suffix = $this->session()->pull(self::SUFFIX_SESSION_KEY);

        return new CreateInviteData(
            prefix: $this->string('prefix')->toString(),
            suffix: is_string($suffix) ? $suffix : null,
            maxUses: $this->integer('max_uses'),
            expiresAt: $days !== null ? now()->addDays($days) : null,
        );
    }
}

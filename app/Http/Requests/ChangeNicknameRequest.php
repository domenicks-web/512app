<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChangeNicknameRequest extends FormRequest
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
            'nickname' => [
                'required',
                'string',
                'min:'.config('game.onboarding.nickname_min_length'),
                'max:'.config('game.onboarding.nickname_max_length'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nickname.required' => 'Escolha um nickname.',
            'nickname.min' => 'O nickname precisa de pelo menos :min letras.',
            'nickname.max' => 'O nickname pode ter no máximo :max letras.',
        ];
    }

    public function nickname(): string
    {
        return $this->string('nickname')->trim()->toString();
    }
}

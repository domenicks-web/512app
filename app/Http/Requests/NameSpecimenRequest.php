<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class NameSpecimenRequest extends FormRequest
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
                'min:'.config('game.specimen.nickname_min_length'),
                'max:'.config('game.specimen.nickname_max_length'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nickname.required' => 'Escolha um apelido.',
            'nickname.min' => 'O apelido precisa de pelo menos :min letra.',
            'nickname.max' => 'O apelido pode ter no máximo :max letras.',
        ];
    }

    public function nickname(): string
    {
        return $this->string('nickname')->trim()->toString();
    }
}

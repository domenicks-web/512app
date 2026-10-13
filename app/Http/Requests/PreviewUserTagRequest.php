<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewUserTagRequest extends FormRequest
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

    public function nickname(): string
    {
        return $this->string('nickname')->trim()->toString();
    }
}

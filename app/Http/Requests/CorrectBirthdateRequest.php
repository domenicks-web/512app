<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class CorrectBirthdateRequest extends FormRequest
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
            'birthdate' => ['required', 'date', 'before:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'birthdate.required' => 'Escolha a data de nascimento.',
            'birthdate.date' => 'Essa data não parece certa.',
            'birthdate.before' => 'A data precisa ser antes de hoje.',
        ];
    }

    public function birthdate(): Carbon
    {
        return Carbon::parse($this->string('birthdate')->toString());
    }
}

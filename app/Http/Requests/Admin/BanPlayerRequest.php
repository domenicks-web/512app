<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Banir é permanente: motivo e "Nick#1234" digitado, como mudar o papel. */
class BanPlayerRequest extends FormRequest
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
        /** @var User $target */
        $target = $this->route('user');
        $expected = $target->nicknameWithTag() ?? $target->email;

        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'confirm' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) use ($expected): void {
                if (mb_strtolower(trim((string) $value)) !== mb_strtolower($expected)) {
                    $fail("Digite {$expected} pra confirmar.");
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Escreva o motivo.',
            'reason.min' => 'Escreva um motivo com pelo menos 5 letras.',
            'confirm.required' => 'Digite o nickname com a tag pra confirmar.',
        ];
    }
}

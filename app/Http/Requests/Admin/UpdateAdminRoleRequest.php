<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Mudar o papel exige digitar "Nick#1234" do alvo (o nickname sozinho se
 * repete). Compara sem diferenciar maiúscula e ignorando espaço nas pontas.
 */
class UpdateAdminRoleRequest extends FormRequest
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
            'is_admin' => ['required', 'boolean'],
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
            'confirm.required' => 'Digite o nickname com a tag pra confirmar.',
        ];
    }
}

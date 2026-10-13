<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Invite;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ValidateInviteRequest extends FormRequest
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
            'invite_code' => ['required', 'string', 'max:64'],
        ];
    }

    /**
     * Mesma mensagem pra "não existe" e "não dá mais pra usar": não vale a
     * pena dizer pra quem está chutando código qual dos dois aconteceu.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $invite = Invite::where('code', $this->inviteCode())->first();

                if ($invite === null || ! $invite->isRedeemable()) {
                    $validator->errors()->add('invite_code', 'Esse convite não existe, expirou, foi revogado ou já atingiu o limite de usos.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invite_code.required' => 'Digite o código do seu convite.',
        ];
    }

    public function inviteCode(): string
    {
        return $this->string('invite_code')->toString();
    }
}

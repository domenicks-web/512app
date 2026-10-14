<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Profile\ListAvatarOptions;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Avatar: um dos parceiros fixos ou uma espécie da coleção do jogador
 * (shiny só com um espécime shiny dela).
 */
class UpdateAvatarRequest extends FormRequest
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
            'avatar_species_id' => ['required', 'integer'],
            'avatar_is_shiny' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var User $user */
                $user = $this->user();

                if (! app(ListAvatarOptions::class)->allows($user, $this->integer('avatar_species_id'), $this->boolean('avatar_is_shiny'))) {
                    $validator->errors()->add('avatar_species_id', 'Esse parceiro não está na sua coleção.');
                }
            },
        ];
    }
}

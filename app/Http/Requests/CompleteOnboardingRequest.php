<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Data\CompleteOnboardingData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CompleteOnboardingRequest extends FormRequest
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
            'nickname' => ['required', 'string', 'min:2', 'max:20'],
            'birthdate' => ['required', 'date', 'before:today'],
            'avatar_species_id' => ['required', 'integer', Rule::in(config('game.onboarding.avatar_species_ids'))],
        ];
    }

    public function toCompleteOnboardingData(): CompleteOnboardingData
    {
        return new CompleteOnboardingData(
            nickname: $this->string('nickname')->toString(),
            birthdate: Carbon::parse($this->string('birthdate')->toString()),
            avatarSpeciesId: $this->integer('avatar_species_id'),
        );
    }
}

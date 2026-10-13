<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Data\CompleteOnboardingData;
use App\Http\Controllers\Onboarding\PreviewUserTagController;
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
            'nickname' => [
                'required',
                'string',
                'min:'.config('game.onboarding.nickname_min_length'),
                'max:'.config('game.onboarding.nickname_max_length'),
            ],
            'birthdate' => ['required', 'date', 'before:today'],
            'avatar_species_id' => ['required', 'integer', Rule::in(config('game.onboarding.avatar_species_ids'))],
        ];
    }

    public function toCompleteOnboardingData(): CompleteOnboardingData
    {
        $nickname = $this->string('nickname')->trim()->toString();

        /** @var array{nickname: string, tag: string}|null $preview */
        $preview = $this->session()->get(PreviewUserTagController::SESSION_KEY);

        return new CompleteOnboardingData(
            nickname: $nickname,
            birthdate: Carbon::parse($this->string('birthdate')->toString()),
            avatarSpeciesId: $this->integer('avatar_species_id'),
            previewedTag: $preview !== null && $preview['nickname'] === $nickname ? $preview['tag'] : null,
        );
    }
}

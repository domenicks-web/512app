<?php

declare(strict_types=1);

namespace App\Actions;

use App\Actions\Data\CompleteOnboardingData;
use App\Models\User;

class CompleteOnboarding
{
    public function __construct(private readonly GenerateUserTag $generateUserTag = new GenerateUserTag) {}

    public function handle(User $user, CompleteOnboardingData $data): User
    {
        $user->update([
            'nickname' => $data->nickname,
            'tag' => $this->resolveTag($data),
            'birthdate' => $data->birthdate,
            'avatar_species_id' => $data->avatarSpeciesId,
        ]);

        return $user->refresh();
    }

    /**
     * Mantém a tag que o jogador viu girar no cartão se o par
     * (nickname, tag) ainda estiver livre; senão sorteia outra.
     */
    private function resolveTag(CompleteOnboardingData $data): string
    {
        $isPreviewStillFree = $data->previewedTag !== null
            && ! User::where('nickname', $data->nickname)->where('tag', $data->previewedTag)->exists();

        return $isPreviewStillFree ? $data->previewedTag : $this->generateUserTag->handle($data->nickname);
    }
}

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
            'tag' => $this->generateUserTag->handle($data->nickname),
            'birthdate' => $data->birthdate,
            'avatar_species_id' => $data->avatarSpeciesId,
        ]);

        return $user->refresh();
    }
}

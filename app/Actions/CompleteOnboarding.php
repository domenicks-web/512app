<?php

declare(strict_types=1);

namespace App\Actions;

use App\Actions\Data\CompleteOnboardingData;
use App\Actions\Friends\BefriendInviter;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompleteOnboarding
{
    public function __construct(
        private readonly GenerateUserTag $generateUserTag = new GenerateUserTag,
        private readonly BefriendInviter $befriendInviter = new BefriendInviter,
    ) {}

    public function handle(User $user, CompleteOnboardingData $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user->update([
                'nickname' => $data->nickname,
                'tag' => $this->resolveTag($data),
                'birthdate' => $data->birthdate,
                'avatar_species_id' => $data->avatarSpeciesId,
            ]);

            $this->befriendInviter->handle($user);

            return $user->refresh();
        });
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

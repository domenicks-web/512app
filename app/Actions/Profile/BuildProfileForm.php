<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;

/**
 * Estado da tela de perfil: dados atuais, quando o nickname libera, se a
 * data já foi corrigida, troca de email pendente e as opções de avatar
 * (parceiros fixos e espécies da coleção).
 */
class BuildProfileForm
{
    public function __construct(private readonly ListAvatarOptions $avatarOptions) {}

    /**
     * @return array{
     *     profile: array{nickname: string, tag: string, avatarSpeciesId: int|null, avatarIsShiny: bool, birthdate: string|null, isBirthdateLocked: bool, email: string, isEmailVerified: bool, pendingEmail: string|null, nicknameChangeableAt: string|null},
     *     avatarOptions: array<int, array{id: int, name: string, sprite_path: string, is_shiny: bool}>,
     *     collectionAvatarOptions: list<array{id: int, name: string, sprite_path: string, is_shiny: bool}>,
     *     nicknameCooldownDays: int,
     *     nicknameMaxLength: int,
     *     birthYearRange: array{int, int},
     *     emailChangeLinkMinutes: int,
     * }
     */
    public function handle(User $user): array
    {
        $avatarOptions = $this->avatarOptions->handle($user);
        $changeableAt = ChangeNickname::changeableAt($user);

        return [
            'profile' => [
                'nickname' => (string) $user->nickname,
                'tag' => (string) $user->tag,
                'avatarSpeciesId' => $user->avatar_species_id,
                'avatarIsShiny' => $user->avatar_is_shiny,
                'birthdate' => $user->birthdate?->toDateString(),
                'isBirthdateLocked' => $user->birthdate_corrected_at !== null,
                'email' => $user->email,
                'isEmailVerified' => $user->email_verified_at !== null,
                'pendingEmail' => $user->pending_email,
                'nicknameChangeableAt' => $changeableAt !== null && $changeableAt->isFuture()
                    ? $changeableAt->copy()->timezone(config('game.display_timezone'))->toDateString()
                    : null,
            ],
            'avatarOptions' => $avatarOptions['partners'],
            'collectionAvatarOptions' => $avatarOptions['collection'],
            'nicknameCooldownDays' => (int) config('game.profile.nickname_cooldown_days'),
            'nicknameMaxLength' => (int) config('game.onboarding.nickname_max_length'),
            'birthYearRange' => [(int) config('game.onboarding.birth_year_min'), now()->year],
            'emailChangeLinkMinutes' => (int) config('game.profile.email_change_link_minutes'),
        ];
    }
}

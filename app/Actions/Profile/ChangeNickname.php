<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Actions\GenerateUserTag;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Troca o nickname respeitando o intervalo de `game.profile.nickname_cooldown_days`
 * desde a última troca (o nickname do onboarding não conta). Mantém a tag
 * se o par (nickname novo, tag) estiver livre; senão sorteia outra.
 */
class ChangeNickname
{
    public function __construct(private readonly GenerateUserTag $generateUserTag = new GenerateUserTag) {}

    public function handle(User $user, string $nickname, CarbonInterface $now): void
    {
        if ($nickname === $user->nickname) {
            $this->fail('Esse já é o seu nickname.');
        }

        $changeableAt = self::changeableAt($user);

        if ($changeableAt !== null && $now->lt($changeableAt)) {
            $this->fail('Você poderá trocar de novo em '.$changeableAt->copy()->timezone(config('game.display_timezone'))->format('d/m').'.');
        }

        $isTagFree = ! User::query()
            ->where('nickname', $nickname)
            ->where('tag', $user->tag)
            ->whereKeyNot($user->id)
            ->exists();

        $user->update([
            'nickname' => $nickname,
            'tag' => $isTagFree ? $user->tag : $this->generateUserTag->handle($nickname),
        ]);
        $user->forceFill(['nickname_changed_at' => $now])->save();
    }

    /** Quando o jogador pode trocar de novo, ou `null` se já pode. */
    public static function changeableAt(User $user): ?CarbonInterface
    {
        return $user->nickname_changed_at?->copy()->addDays((int) config('game.profile.nickname_cooldown_days'));
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['nickname' => $message]);
    }
}

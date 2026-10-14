<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

/** O espécime de maior valor do jogador, pro destaque da Home (ordem do FindTopSpecimens). */
class FindBestSpecimen
{
    public function __construct(private readonly FindTopSpecimens $top = new FindTopSpecimens) {}

    /**
     * @return array{speciesName: string, spritePath: string, isShiny: bool, rarityTier: string, mintNumber: int}|null
     */
    public function handle(User $user): ?array
    {
        $best = $this->top->handle($user, 1)[0] ?? null;

        if ($best === null) {
            return null;
        }

        return [
            'speciesName' => $best['speciesName'],
            'spritePath' => $best['spritePath'],
            'isShiny' => $best['isShiny'],
            'rarityTier' => $best['rarityTier'],
            'mintNumber' => $best['mintNumber'],
        ];
    }
}

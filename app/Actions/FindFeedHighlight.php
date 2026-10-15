<?php

declare(strict_types=1);

namespace App\Actions;

/**
 * O destaque do feed na Home: a abertura boa mais recente do grupo, com o
 * sprite e o avatar de quem abriu, e as anteriores (só o sprite) pra mostrar
 * que o feed tem histórico.
 */
class FindFeedHighlight
{
    /** Quantas aberturas anteriores aparecem em miniatura no card. */
    private const RECENT = 3;

    public function __construct(private readonly BuildGroupFeed $feed) {}

    /**
     * @return array{isShiny: bool, trainerNickname: string, trainerAvatarSpritePath: string|null, speciesName: string, rarityTier: string, spritePath: string, mintNumber: int, caughtAgo: string, recent: list<array{spritePath: string, isShiny: bool, rarityTier: string}>}|null
     */
    public function handle(): ?array
    {
        $entries = $this->feed->openings(self::RECENT + 1);
        $entry = $entries[0] ?? null;

        if ($entry === null) {
            return null;
        }

        return [
            'isShiny' => $entry['isShiny'],
            'trainerNickname' => $entry['trainerNickname'],
            'trainerAvatarSpritePath' => $entry['trainerAvatarSpritePath'],
            'speciesName' => $entry['speciesName'],
            'rarityTier' => $entry['rarityTier'],
            'spritePath' => $entry['spritePath'],
            'mintNumber' => $entry['mintNumber'],
            'caughtAgo' => $entry['caughtAgo'],
            'recent' => array_map(fn (array $older): array => [
                'spritePath' => $older['spritePath'],
                'isShiny' => $older['isShiny'],
                'rarityTier' => $older['rarityTier'],
            ], array_slice($entries, 1)),
        ];
    }
}

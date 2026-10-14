<?php

declare(strict_types=1);

namespace App\Actions;

/** O destaque do feed na Home: só a abertura boa mais recente do grupo. */
class FindFeedHighlight
{
    public function __construct(private readonly BuildGroupFeed $feed) {}

    /**
     * @return array{isShiny: bool, trainerNickname: string, speciesName: string, mintNumber: int, caughtAgo: string}|null
     */
    public function handle(): ?array
    {
        $entry = $this->feed->openings(1)[0] ?? null;

        if ($entry === null) {
            return null;
        }

        return [
            'isShiny' => $entry['isShiny'],
            'trainerNickname' => $entry['trainerNickname'],
            'speciesName' => $entry['speciesName'],
            'mintNumber' => $entry['mintNumber'],
            'caughtAgo' => $entry['caughtAgo'],
        ];
    }
}

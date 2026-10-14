<?php

declare(strict_types=1);

use App\Actions\DrawPack;
use App\Enums\RarityTier;

/** Tiers com poucas espécies fictícias, em ordem de dex. */
function speciesByTier(): array
{
    return [
        RarityTier::Common->value => [1, 2, 3, 4],
        RarityTier::Uncommon->value => [10, 11, 12],
        RarityTier::Rare->value => [20, 21],
        RarityTier::Legendary->value => [30],
    ];
}

function tierOf(int $speciesId): RarityTier
{
    foreach (speciesByTier() as $tier => $ids) {
        if (in_array($speciesId, $ids, true)) {
            return RarityTier::from($tier);
        }
    }

    throw new LogicException("Espécie {$speciesId} fora da tabela.");
}

it('sorteia sempre o mesmo pack pra mesma seed, client seed e nonce', function () {
    $first = (new DrawPack)->handle('server-seed', 'client', 7, speciesByTier());
    $second = (new DrawPack)->handle('server-seed', 'client', 7, speciesByTier());

    expect($first)->toEqual($second)->toHaveCount(5);
});

it('muda o sorteio quando a client seed ou o nonce mudam', function () {
    $base = (new DrawPack)->handle('server-seed', 'client', 7, speciesByTier());

    expect((new DrawPack)->handle('server-seed', 'outra', 7, speciesByTier()))->not->toEqual($base)
        ->and((new DrawPack)->handle('server-seed', 'client', 8, speciesByTier()))->not->toEqual($base);
});

it('usa o HMAC por slot do SPEC como seed de cada espécime', function () {
    $draws = (new DrawPack)->handle('server-seed', 'client', 7, speciesByTier());

    foreach ($draws as $draw) {
        expect($draw->seed)->toBe(hash_hmac('sha256', "client:7:slot:{$draw->slot}", 'server-seed'))
            ->and(tierOf($draw->speciesId))->toBe($draw->tier);
    }
});

it('nunca dá comum no último slot', function () {
    foreach (range(0, 1999) as $nonce) {
        $draws = (new DrawPack)->handle('seed-garantido', 'client', $nonce, speciesByTier());

        expect($draws[4]->tier)->not->toBe(RarityTier::Common);
    }
});

it('distribui os tiers dos slots livres conforme os pesos do config', function () {
    $counts = array_fill_keys(array_map(fn (RarityTier $tier) => $tier->value, RarityTier::cases()), 0);
    $total = 0;

    foreach (range(0, 4999) as $nonce) {
        foreach (array_slice((new DrawPack)->handle('seed-amostra', 'client', $nonce, speciesByTier()), 0, 4) as $draw) {
            $counts[$draw->tier->value]++;
            $total++;
        }
    }

    foreach (config('game.rarity.weights') as $tier => $weight) {
        expect($counts[$tier] / $total * 100)->toEqualWithDelta($weight, max(1.0, $weight * 0.15));
    }
});

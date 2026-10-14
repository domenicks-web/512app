<?php

declare(strict_types=1);

use App\Enums\Nature;

it('mostra o nome dos jogos, em inglês', function () {
    expect(Nature::Impish->label())->toBe('Impish')
        ->and(Nature::Mild->label())->toBe('Mild');
});

it('diz o efeito que a natureza tem nos jogos, ou que é neutra', function () {
    expect(Nature::Impish->effect())->toBe('+Def −SpA')
        ->and(Nature::Mild->effect())->toBe('+SpA −Def')
        ->and(Nature::Adamant->effect())->toBe('+Atk −SpA')
        ->and(Nature::Timid->effect())->toBe('+Spe −Atk')
        ->and(Nature::Sassy->effect())->toBe('+SpD −Spe');

    $neutral = array_filter(Nature::cases(), fn (Nature $nature): bool => $nature->effect() === 'Neutra');

    expect(array_values($neutral))->toBe([Nature::Hardy, Nature::Docile, Nature::Serious, Nature::Bashful, Nature::Quirky]);
});

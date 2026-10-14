<?php

declare(strict_types=1);

use App\Enums\Nature;

it('dá um nome em português, único, pra cada natureza', function () {
    $labels = array_map(fn (Nature $nature): string => $nature->label(), Nature::cases());

    expect($labels)->toHaveCount(25)
        ->and(array_unique($labels))->toHaveCount(25)
        ->and(Nature::Impish->label())->toBe('Arteira')
        ->and(Nature::Mild->label())->toBe('Branda');
});

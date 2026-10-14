<?php

declare(strict_types=1);

use App\Actions\Trainer\CompareSpecimens;
use App\Enums\Nature;
use App\Enums\SizeClass;
use App\Models\Specimen;

function comparable(array $ivs, int $mint): Specimen
{
    return (new Specimen)->forceFill([
        'iv_hp' => $ivs[0], 'iv_atk' => $ivs[1], 'iv_def' => $ivs[2],
        'iv_spa' => $ivs[3], 'iv_spd' => $ivs[4], 'iv_spe' => $ivs[5],
        'iv_total' => array_sum($ivs),
        'mint_number' => $mint,
        'size_class' => SizeClass::M,
        'nature' => Nature::Hardy,
    ]);
}

it('diz quem ganha em cada IV, no total e no mint, com empate neutro', function () {
    $mine = comparable([31, 10, 5, 5, 0, 0], 3);
    $theirs = comparable([20, 10, 9, 1, 0, 30], 7);

    $result = (new CompareSpecimens)->handle($mine, $theirs);

    expect(array_column($result['stats'], 'winner', 'key'))->toBe([
        'ivTotal' => 'theirs',
        'ivHp' => 'mine',
        'ivAtk' => 'tie',
        'ivDef' => 'theirs',
        'ivSpa' => 'mine',
        'ivSpd' => 'tie',
        'ivSpe' => 'theirs',
    ])
        ->and($result['stats'][0])->toMatchArray(['mine' => 51, 'theirs' => 70])
        ->and($result['mint'])->toBe(['mine' => 3, 'theirs' => 7, 'winner' => 'mine'])
        ->and($result['score'])->toBe(['mine' => 2, 'theirs' => 3, 'total' => 7])
        ->and(array_column($result['info'], 'key'))->toBe(['size', 'nature'])
        ->and($result['info'][1])->toMatchArray(['mine' => 'Hardy', 'mineNote' => 'Neutra']);
});

it('comparar um espécime com ele mesmo empata tudo', function () {
    $specimen = comparable([1, 2, 3, 4, 5, 6], 1);

    $result = (new CompareSpecimens)->handle($specimen, $specimen);

    expect(array_unique(array_column($result['stats'], 'winner')))->toBe(['tie'])
        ->and($result['mint']['winner'])->toBe('tie')
        ->and($result['score'])->toBe(['mine' => 0, 'theirs' => 0, 'total' => 7]);
});

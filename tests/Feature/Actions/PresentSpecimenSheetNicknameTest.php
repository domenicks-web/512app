<?php

declare(strict_types=1);

use App\Actions\PresentSpecimenSheet;
use App\Actions\Trades\AcceptTrade;
use App\Models\Specimen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

require_once __DIR__.'/Trades/helpers.php';

uses(RefreshDatabase::class);

it('mostra o apelido de cada dono no histórico e se quem vê pode dar o seu', function () {
    [$a, $b] = friends();
    $specimen = owned($a, ['nickname' => 'João']);
    app(AcceptTrade::class)->handle(propose($a, $b, [$specimen->id], [owned($b)->id]), $b);
    $specimen->fresh()->forceFill(['nickname' => 'Raio'])->save();
    app(AcceptTrade::class)->handle(propose($b, $a, [$specimen->id], [owned($a)->id]), $a);

    $sheet = fn ($viewer) => app(PresentSpecimenSheet::class)->handle(
        Specimen::query()->with(PresentSpecimenSheet::RELATIONS)->findOrFail($specimen->id),
        Request::create('/')->setUserResolver(fn () => $viewer),
    );

    $forA = $sheet($a);
    expect(array_column($forA['history'], 'specimenNickname'))->toBe(['João', 'Raio', null])
        ->and($forA['nickname'])->toBeNull()
        ->and($forA['canName'])->toBeTrue()
        ->and($sheet($b)['canName'])->toBeFalse();
});

it('não deixa o dono dar apelido de novo depois de dar', function () {
    [$a] = friends();
    $specimen = owned($a, ['nickname' => 'João']);

    $sheet = app(PresentSpecimenSheet::class)->handle(
        Specimen::query()->with(PresentSpecimenSheet::RELATIONS)->findOrFail($specimen->id),
        Request::create('/')->setUserResolver(fn () => $a),
    );

    expect($sheet['canName'])->toBeFalse()
        ->and($sheet['history'][0]['specimenNickname'])->toBe('João');
});

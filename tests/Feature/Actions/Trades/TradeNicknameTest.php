<?php

declare(strict_types=1);

use App\Actions\NameSpecimen;
use App\Actions\Trades\AcceptTrade;
use App\Models\SpecimenTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

it('na troca o apelido vai pro histórico e o novo dono pode dar outro', function () {
    [$a, $b] = friends();
    $mine = owned($a, ['nickname' => 'João']);
    $theirs = owned($b);
    app(AcceptTrade::class)->handle(propose($a, $b, [$mine->id], [$theirs->id]), $b);

    expect($mine->fresh()->nickname)->toBeNull()
        ->and(SpecimenTransfer::query()->where('specimen_id', $mine->id)->value('nickname'))->toBe('João')
        ->and(SpecimenTransfer::query()->where('specimen_id', $theirs->id)->value('nickname'))->toBeNull();

    app(NameSpecimen::class)->handle($mine->fresh(), $b, 'Raio');
    expect($mine->fresh()->nickname)->toBe('Raio');
});

<?php

declare(strict_types=1);

use App\Actions\NameSpecimen;
use App\Exceptions\SpecimenNamingNotAllowedException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/Trades/helpers.php';

uses(RefreshDatabase::class);

it('grava o apelido que o dono dá', function () {
    $owner = User::factory()->create();
    $specimen = owned($owner);

    app(NameSpecimen::class)->handle($specimen, $owner, 'João');

    expect($specimen->fresh()->nickname)->toBe('João');
});

it('recusa quem não é o dono', function () {
    $specimen = owned(User::factory()->create());

    app(NameSpecimen::class)->handle($specimen, User::factory()->create(), 'João');
})->throws(SpecimenNamingNotAllowedException::class, 'Esse espécime não é seu.');

it('não deixa dar um segundo apelido', function () {
    $owner = User::factory()->create();
    $specimen = owned($owner, ['nickname' => 'João']);

    app(NameSpecimen::class)->handle($specimen, $owner, 'Raio');
})->throws(SpecimenNamingNotAllowedException::class, 'Esse espécime já tem apelido.');

it('confere a trava na linha do banco, não no objeto em memória', function () {
    $owner = User::factory()->create();
    $specimen = owned($owner);
    $stale = $specimen->replicate()->setRawAttributes($specimen->getAttributes(), true);
    $specimen->forceFill(['nickname' => 'João'])->save();

    app(NameSpecimen::class)->handle($stale, $owner, 'Raio');
})->throws(SpecimenNamingNotAllowedException::class);

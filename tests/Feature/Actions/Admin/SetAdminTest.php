<?php

declare(strict_types=1);

use App\Actions\Admin\SetAdmin;
use App\Exceptions\AdminChangeNotAllowedException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('promove e rebaixa', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();

    (new SetAdmin)->handle($admin, $player, true);
    expect($player->fresh()->is_admin)->toBeTrue();

    (new SetAdmin)->handle($admin, $player, false);
    expect($player->fresh()->is_admin)->toBeFalse();
});

it('recusa mexer no próprio acesso', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    try {
        (new SetAdmin)->handle($admin, $admin, false);
        $this->fail('Deveria ter recusado.');
    } catch (AdminChangeNotAllowedException $exception) {
        expect($exception->isSelfChange)->toBeTrue();
    }

    expect($admin->fresh()->is_admin)->toBeTrue();
});

it('recusa rebaixar o último admin, mesmo pela linha de comando', function () {
    $lastAdmin = User::factory()->admin()->create();

    try {
        (new SetAdmin)->handle(null, $lastAdmin, false);
        $this->fail('Deveria ter recusado.');
    } catch (AdminChangeNotAllowedException $exception) {
        expect($exception->isSelfChange)->toBeFalse()
            ->and($exception->getMessage())->toContain('último admin');
    }

    expect($lastAdmin->fresh()->is_admin)->toBeTrue();
});

it('segura o último admin quando o ator já perdeu o acesso noutra aba', function () {
    $formerAdmin = User::factory()->create();
    $lastAdmin = User::factory()->admin()->create();

    (new SetAdmin)->handle($formerAdmin, $lastAdmin, false);
})->throws(AdminChangeNotAllowedException::class, 'último admin');

it('rebaixar quem já é jogador não esbarra na trava do último admin', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();

    (new SetAdmin)->handle($admin, $player, false);

    expect($player->fresh()->is_admin)->toBeFalse()
        ->and($admin->fresh()->is_admin)->toBeTrue();
});

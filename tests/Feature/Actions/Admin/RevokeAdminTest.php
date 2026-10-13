<?php

declare(strict_types=1);

use App\Actions\Admin\RevokeAdmin;
use App\Exceptions\AdminChangeNotAllowedException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('recusa rebaixar a si mesmo', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    (new RevokeAdmin)->handle($admin, $admin);
})->throws(AdminChangeNotAllowedException::class, 'próprio');

it('recusa rebaixar o último admin', function () {
    // O ator aqui já perdeu o admin (ex: foi rebaixado noutra aba), mas a
    // request chegou mesmo assim: a trava do último admin segura sozinha.
    $formerAdmin = User::factory()->create();
    $lastAdmin = User::factory()->admin()->create();

    try {
        (new RevokeAdmin)->handle($formerAdmin, $lastAdmin);
        $this->fail('Deveria ter recusado.');
    } catch (AdminChangeNotAllowedException $exception) {
        expect($exception->getMessage())->toContain('último admin');
    }

    expect($lastAdmin->fresh()->is_admin)->toBeTrue();
});

it('é no-op quando o alvo já não é admin', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();

    $result = (new RevokeAdmin)->handle($admin, $player);

    expect($result->fresh()->is_admin)->toBeFalse()
        ->and($admin->fresh()->is_admin)->toBeTrue();
});

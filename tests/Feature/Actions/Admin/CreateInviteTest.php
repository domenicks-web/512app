<?php

declare(strict_types=1);

use App\Actions\Admin\CreateInvite;
use App\Actions\Data\CreateInviteData;
use App\Actions\GenerateInviteCode;
use App\Enums\InviteStatus;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('gera o código com o nickname do criador e sufixo sem caracteres ambíguos', function () {
    $creator = User::factory()->create(['nickname' => 'Ramon']);

    $code = (new GenerateInviteCode)->handle($creator);

    expect($code)->toMatch('/^RAMON-[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{4}$/');
});

it('limpa acento e símbolo do nickname no prefixo', function () {
    $creator = User::factory()->create(['nickname' => 'João.Zé!']);

    expect((new GenerateInviteCode)->handle($creator))->toStartWith('JOAOZE-');
});

it('usa o prefixo reserva quando o nickname não tem nada aproveitável', function () {
    $creator = User::factory()->withoutProfile()->create();

    expect((new GenerateInviteCode)->handle($creator))->toStartWith('CONVITE-');
});

it('gera códigos diferentes em sequência', function () {
    $creator = User::factory()->create(['nickname' => 'Ramon']);
    $action = new CreateInvite;

    $codes = collect(range(1, 50))
        ->map(fn () => $action->handle($creator, new CreateInviteData(maxUses: 1, expiresAt: null))->code);

    expect($codes->unique())->toHaveCount(50);
});

it('calcula o status do convite', function (array $state, InviteStatus $expected) {
    expect(Invite::factory()->create($state)->status())->toBe($expected);
})->with([
    'ativo' => [['max_uses' => 1, 'uses_count' => 0], InviteStatus::Active],
    'esgotado' => [['max_uses' => 1, 'uses_count' => 1], InviteStatus::Exhausted],
    'expirado' => [['expires_at' => now()->subMinute()], InviteStatus::Expired],
    'revogado vence expirado' => [['expires_at' => now()->subMinute(), 'revoked_at' => now()], InviteStatus::Revoked],
    'infinito ativo' => [['max_uses' => null, 'uses_count' => 999], InviteStatus::Active],
]);

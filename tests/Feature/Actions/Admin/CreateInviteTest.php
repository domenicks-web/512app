<?php

declare(strict_types=1);

use App\Actions\Admin\CreateInvite;
use App\Actions\Admin\DefaultInvitePrefix;
use App\Actions\Admin\GenerateInviteSuffix;
use App\Actions\Data\CreateInviteData;
use App\Enums\InviteStatus;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Sorteador previsível: devolve os sufixos na ordem dada. */
function fixedSuffixes(string ...$suffixes): GenerateInviteSuffix
{
    return new class($suffixes) extends GenerateInviteSuffix
    {
        /** @param list<string> $suffixes */
        public function __construct(private array $suffixes) {}

        public function handle(): string
        {
            return array_shift($this->suffixes) ?? throw new LogicException('Acabaram os sufixos.');
        }
    };
}

function inviteData(array $overrides = []): CreateInviteData
{
    return new CreateInviteData(...array_merge([
        'prefix' => 'RAMON',
        'suffix' => null,
        'maxUses' => 1,
        'expiresAt' => null,
    ], $overrides));
}

it('sorteia sufixo de 4 caracteres sem I, O, 0 e 1', function () {
    $suffixes = collect(range(1, 300))->map(fn () => (new GenerateInviteSuffix)->handle());

    expect($suffixes->every(fn (string $suffix) => preg_match('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{4}$/', $suffix) === 1))->toBeTrue();
});

it('sugere o nickname limpo como prefixo, cortado em 8', function (?string $nickname, string $prefix) {
    $creator = User::factory()->create(['nickname' => $nickname]);

    expect((new DefaultInvitePrefix)->handle($creator))->toBe($prefix);
})->with([
    'simples' => ['Ramon', 'RAMON'],
    'acento e símbolo' => ['João.Zé!', 'JOAOZE'],
    'longo' => ['Bulbasaurinho', 'BULBASAU'],
    'nada aproveitável' => ['!!!', 'CONVITE'],
]);

it('grava o sufixo da prévia quando o código está livre', function () {
    $creator = User::factory()->admin()->create();

    $invite = (new CreateInvite(fixedSuffixes()))->handle($creator, inviteData(['suffix' => '7X2K', 'maxUses' => 3, 'expiresAt' => now()->addDays(7)]));

    expect($invite->code)->toBe('RAMON-7X2K')
        ->and($invite->created_by)->toBe($creator->id)
        ->and($invite->max_uses)->toBe(3)
        ->and($invite->status())->toBe(InviteStatus::Active);
});

it('sorteia outro sufixo quando o código já existe', function () {
    $creator = User::factory()->admin()->create();
    Invite::factory()->create(['code' => 'RAMON-7X2K']);
    Invite::factory()->create(['code' => 'RAMON-AAAA']);

    $invite = (new CreateInvite(fixedSuffixes('AAAA', 'BBBB')))->handle($creator, inviteData(['suffix' => '7X2K']));

    expect($invite->code)->toBe('RAMON-BBBB');
});

it('deriva o status na ordem revogado, esgotado, expirado, ativo', function (array $attributes, InviteStatus $status) {
    $invite = Invite::factory()->create($attributes);

    expect($invite->status())->toBe($status)
        ->and(Invite::active()->whereKey($invite->id)->exists())->toBe($status === InviteStatus::Active);
})->with([
    'ativo' => [['max_uses' => 2, 'uses_count' => 1], InviteStatus::Active],
    'esgotado' => [['max_uses' => 1, 'uses_count' => 1], InviteStatus::Exhausted],
    'expirado' => [['expires_at' => now()->subMinute()], InviteStatus::Expired],
    'esgotado vence expirado' => [['max_uses' => 1, 'uses_count' => 1, 'expires_at' => now()->subMinute()], InviteStatus::Exhausted],
    'revogado vence tudo' => [['max_uses' => 1, 'uses_count' => 1, 'revoked_at' => now()], InviteStatus::Revoked],
    'validade no futuro' => [['expires_at' => now()->addDay()], InviteStatus::Active],
]);

<?php

declare(strict_types=1);

use App\Actions\Admin\ResendVerificationEmail;
use App\Actions\Admin\SetInviteQuota;
use App\Actions\Admin\SetPlayerPassword;
use App\Exceptions\AdminChangeNotAllowedException;
use App\Mail\PasswordChangedMail;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('jogador novo nasce com a cota padrão', function () {
    expect(User::factory()->create()->fresh()->invite_quota)->toBe(config('game.invites.default_quota'));
});

it('muda a cota de jogador comum e recusa em admin', function () {
    $player = User::factory()->create();
    (new SetInviteQuota)->handle($player, 5);
    expect($player->fresh()->invite_quota)->toBe(5);

    (new SetInviteQuota)->handle(User::factory()->admin()->create(), 5);
})->throws(AdminChangeNotAllowedException::class, 'sem cota');

it('reenvia a confirmação só pra email não confirmado', function () {
    Notification::fake();
    $unverified = User::factory()->unverified()->create();
    $verified = User::factory()->create();

    expect((new ResendVerificationEmail)->handle($unverified))->toBeTrue()
        ->and((new ResendVerificationEmail)->handle($verified))->toBeFalse();

    Notification::assertSentTo($unverified, VerifyEmail::class);
    Notification::assertNotSentTo($verified, VerifyEmail::class);
});

it('troca a senha de outro jogador, derruba todas as sessões dele e avisa', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create(['remember_token' => 'antigo']);
    $other = User::factory()->create();
    foreach ([[$player->id, 'a'], [$player->id, 'b'], [$other->id, 'c']] as [$userId, $id]) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => '', 'last_activity' => now()->timestamp]);
    }

    (new SetPlayerPassword)->handle($admin, $player, 'senha-nova-123');

    $player->refresh();
    expect(Hash::check('senha-nova-123', $player->password))->toBeTrue()
        ->and($player->remember_token)->not->toBe('antigo')
        ->and(DB::table('sessions')->pluck('id')->all())->toBe(['c']);
    Mail::assertSent(PasswordChangedMail::class, fn (PasswordChangedMail $mail) => $mail->hasTo($player->email) && $mail->byAdmin);
});

it('não troca a própria senha pelo painel', function () {
    $admin = User::factory()->admin()->create();

    (new SetPlayerPassword)->handle($admin, $admin, 'senha-nova-123');
})->throws(AdminChangeNotAllowedException::class);

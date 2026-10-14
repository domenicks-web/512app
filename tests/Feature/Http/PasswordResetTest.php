<?php

declare(strict_types=1);

use App\Mail\PasswordChangedMail;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('manda o link pra quem tem conta', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'lin@512.test']);

    $this->postJson('/esqueci-a-senha', ['email' => 'Lin@512.test'])->assertNoContent();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('responde igual pra email sem conta, sem contar que não existe', function () {
    Notification::fake();

    $this->postJson('/esqueci-a-senha', ['email' => 'ninguem@512.test'])->assertNoContent();

    Notification::assertNothingSent();
});

it('manda o email no visual do jogo com o link da tela de senha nova', function () {
    $user = User::factory()->create(['email' => 'lin@512.test']);

    $mail = (new ResetPassword('abc123'))->toMail($user);

    expect($mail)->toBeInstanceOf(ResetPasswordMail::class)
        ->and($mail->url)->toBe(route('password.reset.link', ['token' => 'abc123', 'email' => 'lin@512.test']))
        ->and($mail->render())->toContain('60 minutos')->toContain('Criar senha nova');
});

it('tira o token da URL: guarda na sessão e abre a tela limpa', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->get("/nova-senha/{$token}?email={$user->email}")
        ->assertRedirect('/nova-senha')
        ->assertSessionHas('password_reset', ['token' => $token, 'email' => $user->email]);

    $this->get('/nova-senha')
        ->assertInertia(fn (Assert $page) => $page->component('auth/ResetPassword')->where('isLinkValid', true));
});

it('mostra o link como inválido quando o token não confere', function () {
    $user = User::factory()->create();
    Password::createToken($user);

    $this->get("/nova-senha/errado?email={$user->email}");

    $this->get('/nova-senha')->assertInertia(fn (Assert $page) => $page->where('isLinkValid', false));
});

it('troca a senha, derruba todas as sessões, avisa por email e não loga', function () {
    Mail::fake();
    $user = User::factory()->create(['remember_token' => 'antigo']);
    $token = Password::createToken($user);
    DB::table('sessions')->insert(['id' => 'outra', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $this->get("/nova-senha/{$token}?email={$user->email}");
    $this->postJson('/nova-senha', ['password' => 'senha-nova-123', 'password_confirmation' => 'senha-nova-123'])
        ->assertOk()
        ->assertJson(['email' => $user->email]);

    $user->refresh();
    expect(Hash::check('senha-nova-123', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe('antigo')
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->count())->toBe(0);
    $this->assertGuest();
    Mail::assertSent(PasswordChangedMail::class, fn (PasswordChangedMail $mail) => $mail->hasTo($user->email) && $mail->byReset);
});

it('não deixa usar o mesmo link duas vezes', function () {
    Mail::fake();
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->get("/nova-senha/{$token}?email={$user->email}");
    $this->postJson('/nova-senha', ['password' => 'senha-nova-123', 'password_confirmation' => 'senha-nova-123'])->assertOk();

    $this->get("/nova-senha/{$token}?email={$user->email}");
    $this->postJson('/nova-senha', ['password' => 'outra-senha-45', 'password_confirmation' => 'outra-senha-45'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token' => 'Esse link expirou ou já foi usado. Peça outro.']);
});

it('recusa a troca sem passar pelo link', function () {
    $this->postJson('/nova-senha', ['password' => 'senha-nova-123', 'password_confirmation' => 'senha-nova-123'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('token');
});

it('exige a confirmação da senha nova', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->get("/nova-senha/{$token}?email={$user->email}");
    $this->postJson('/nova-senha', ['password' => 'senha-nova-123', 'password_confirmation' => 'outra'])
        ->assertJsonValidationErrors(['password' => 'As duas senhas não batem.']);
});

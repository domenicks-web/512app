<?php

declare(strict_types=1);

use App\Mail\ConfirmNewEmailMail;
use App\Mail\EmailChangedMail;
use App\Mail\PasswordChangedMail;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('renderiza o link de confirmação com a validade', function () {
    $html = (new ConfirmNewEmailMail(User::factory()->create(['nickname' => 'Lin']), 'https://512.test/confirmar', 60))->render();

    expect($html)->toContain('Lin')
        ->toContain('https://512.test/confirmar')
        ->toContain('60 minutos');
});

it('mascara o email novo no aviso pro antigo', function () {
    $html = (new EmailChangedMail(User::factory()->create(), 'ramon@gmail.com'))->render();

    expect($html)->toContain('r***@gmail.com')->not->toContain('ramon@gmail.com');
});

it('mostra a hora da troca de senha no fuso do jogador', function () {
    $html = (new PasswordChangedMail(User::factory()->create(), Carbon::parse('2026-10-14 01:30:00', 'UTC')))->render();

    expect($html)->toContain('13/10 às 22:30');
});

it('manda a confirmação do cadastro no visual do jogo, sem cartão enquanto não tem perfil', function () {
    $user = User::factory()->unverified()->withoutProfile()->create();

    $mail = (new VerifyEmail)->toMail($user);

    expect($mail)->toBeInstanceOf(VerifyEmailMail::class);
    $html = $mail->render();
    expect($html)->toContain('Confirme seu email')
        ->toContain('/verificar-email/'.$user->id)
        ->toContain('60 minutos')
        ->not->toContain('Treinador do 512');
});

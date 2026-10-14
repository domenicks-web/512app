<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function verificationUrl(User $user): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);
}

it('confirma o email, volta pra home e avisa', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(verificationUrl($user))
        ->assertRedirect(route('home'))
        ->assertSessionHas('inertia.flash_data', ['toast' => 'Email confirmado!']);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('avisa quando o email já estava confirmado', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(verificationUrl($user))
        ->assertSessionHas('inertia.flash_data', ['toast' => 'Esse email já estava confirmado.']);
});

<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginUserController;
use App\Http\Controllers\Auth\LogoutUserController;
use App\Http\Controllers\Auth\RegisterUserController;
use App\Http\Controllers\Auth\ShowRegisterController;
use App\Http\Controllers\Auth\ValidateInviteController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Onboarding\CompleteOnboardingController;
use App\Http\Controllers\Onboarding\PreviewUserTagController;
use App\Http\Controllers\Onboarding\ShowOnboardingController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::inertia('login', 'auth/Login')->name('login');
    Route::post('login', [LoginUserController::class, 'store'])->name('login.store');
    Route::get('cadastro', ShowRegisterController::class)->name('register');
    Route::post('cadastro/convite', [ValidateInviteController::class, 'store'])->name('register.invite.store');
    Route::delete('cadastro/convite', [ValidateInviteController::class, 'destroy'])->name('register.invite.destroy');
    Route::post('cadastro', [RegisterUserController::class, 'store'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verificar-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::get('completar-perfil', ShowOnboardingController::class)->name('onboarding.show');
    Route::get('completar-perfil/tag', PreviewUserTagController::class)
        ->middleware('throttle:60,1')
        ->name('onboarding.tag');
    Route::post('completar-perfil', [CompleteOnboardingController::class, 'store'])->name('onboarding.store');
    Route::post('logout', LogoutUserController::class)->name('logout');
});

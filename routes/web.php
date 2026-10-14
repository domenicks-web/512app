<?php

declare(strict_types=1);

use App\Http\Controllers\Collection\ShowCollectionController;
use App\Http\Controllers\Friends\AcceptFriendRequestController;
use App\Http\Controllers\Friends\RemoveFriendshipController;
use App\Http\Controllers\Friends\SendFriendRequestController;
use App\Http\Controllers\Friends\ShowFriendsController;
use App\Http\Controllers\Home\ShowHomeController;
use App\Http\Controllers\Pack\ClientSeedController;
use App\Http\Controllers\Pack\PackController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Profile\ShowProfileController;
use App\Http\Controllers\Ranking\ShowRankingController;
use App\Http\Controllers\Trades\ShowTradesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::get('/', ShowHomeController::class)->name('home');
    Route::get('colecao', ShowCollectionController::class)->name('collection.show');
    Route::get('ranking', ShowRankingController::class)->name('ranking.show');
    Route::get('trocas', ShowTradesController::class)->name('trades.index');
    Route::get('amigos', ShowFriendsController::class)->name('friends.index');

    Route::get('pack', [PackController::class, 'show'])->name('pack.show');
    Route::post('pack/abrir', [PackController::class, 'open'])->name('pack.open');
    Route::post('pack/{opening}/bolas/{slot}', [PackController::class, 'reveal'])->whereNumber('slot')->name('pack.reveal');
    Route::patch('pack/client-seed', [ClientSeedController::class, 'update'])->name('pack.client-seed.update');
    Route::post('amigos/pedidos', SendFriendRequestController::class)->name('friends.requests.store');
    Route::post('amigos/pedidos/{friendship}/aceitar', AcceptFriendRequestController::class)
        ->can('accept', 'friendship')
        ->name('friends.requests.accept');
    Route::delete('amigos/{friendship}', RemoveFriendshipController::class)
        ->can('delete', 'friendship')
        ->name('friends.destroy');

    Route::get('perfil', ShowProfileController::class)->name('profile.edit');
    Route::put('perfil/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::put('perfil/nickname', [ProfileController::class, 'updateNickname'])->name('profile.nickname.update');
    Route::put('perfil/nascimento', [ProfileController::class, 'updateBirthdate'])->name('profile.birthdate.update');
    Route::delete('perfil/email', [ProfileController::class, 'cancelEmail'])->name('profile.email.destroy');
    Route::get('perfil/email/confirmar', [ProfileController::class, 'confirmEmail'])
        ->middleware('signed')
        ->name('profile.email.confirm');

    Route::middleware('throttle:6,1')->group(function () {
        Route::post('perfil/email', [ProfileController::class, 'requestEmail'])->name('profile.email.store');
        Route::put('perfil/senha', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    });
});

if (! app()->isProduction()) {
    Route::inertia('/design-system', 'DesignSystem')->name('design-system');
}

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';

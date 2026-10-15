<?php

declare(strict_types=1);

use App\Http\Controllers\Collection\ShowCollectionController;
use App\Http\Controllers\Collection\ShowCollectionSpeciesController;
use App\Http\Controllers\Feed\ShowFeedController;
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
use App\Http\Controllers\Specimen\ShowSpecimenController;
use App\Http\Controllers\Trades\ShowTradesController;
use App\Http\Controllers\Trades\TradeBuilderController;
use App\Http\Controllers\Trades\TradeController;
use App\Http\Controllers\Trainer\CompareSpecimensController;
use App\Http\Controllers\Trainer\ShowTrainerController;
use App\Http\Controllers\Trainer\ShowTrainerSpeciesController;
use App\Http\Controllers\Verify\VerifyPackController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::get('/', ShowHomeController::class)->name('home');
    Route::get('colecao', ShowCollectionController::class)->name('collection.show');
    Route::get('colecao/{species}', ShowCollectionSpeciesController::class)->name('collection.species');
    Route::get('ranking', ShowRankingController::class)->name('ranking.show');
    Route::get('trocas', ShowTradesController::class)->name('trades.index');
    Route::get('trocas/nova/{friend}', [TradeBuilderController::class, 'create'])->whereNumber('friend')->name('trades.create');
    Route::get('trocas/{trade}/contrapropor', [TradeBuilderController::class, 'counter'])->whereNumber('trade')->name('trades.counter.create');
    Route::post('trocas', [TradeController::class, 'store'])->name('trades.store');
    Route::post('trocas/{trade}/aceitar', [TradeController::class, 'accept'])->whereNumber('trade')->name('trades.accept');
    Route::post('trocas/{trade}/recusar', [TradeController::class, 'decline'])->whereNumber('trade')->name('trades.decline');
    Route::post('trocas/{trade}/cancelar', [TradeController::class, 'cancel'])->whereNumber('trade')->name('trades.cancel');
    Route::post('trocas/{trade}/contrapropor', [TradeController::class, 'counter'])->whereNumber('trade')->name('trades.counter.store');
    Route::post('trocas/{trade}/visto', [TradeController::class, 'seen'])->whereNumber('trade')->name('trades.seen');
    Route::get('amigos', ShowFriendsController::class)->name('friends.index');
    Route::get('feed', ShowFeedController::class)->name('feed.show');

    Route::get('pack', [PackController::class, 'show'])->name('pack.show');
    Route::post('pack/abrir', [PackController::class, 'open'])->name('pack.open');
    Route::post('pack/{opening}/bolas/{slot}', [PackController::class, 'reveal'])->whereNumber('slot')->name('pack.reveal');
    Route::patch('pack/client-seed', [ClientSeedController::class, 'update'])->name('pack.client-seed.update');
    Route::get('treinador/{user}', ShowTrainerController::class)->whereNumber('user')->name('trainer.show');
    Route::get('treinador/{user}/colecao/{species}', ShowTrainerSpeciesController::class)->whereNumber(['user', 'species'])->name('trainer.species');
    Route::get('especime/{specimen}', ShowSpecimenController::class)->whereNumber('specimen')->name('specimens.show');
    Route::get('comparar/{mine}/{theirs}', CompareSpecimensController::class)->whereNumber(['mine', 'theirs'])->name('compare.show');
    Route::get('verificar', [VerifyPackController::class, 'show'])->name('verify.show');
    Route::post('verificar', [VerifyPackController::class, 'store'])->middleware('throttle:30,1')->name('verify.store');
    Route::post('amigos/pedidos', SendFriendRequestController::class)->name('friends.requests.store');
    Route::post('amigos/pedidos/{friendship}/aceitar', AcceptFriendRequestController::class)
        ->can('accept', 'friendship')
        ->name('friends.requests.accept');
    Route::delete('amigos/{friendship}', RemoveFriendshipController::class)
        ->can('delete', 'friendship')
        ->name('friends.destroy');

    Route::inertia('ajustes', 'settings/Show')->name('settings.show');
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

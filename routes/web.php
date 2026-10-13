<?php

declare(strict_types=1);

use App\Http\Controllers\Collection\ShowCollectionController;
use App\Http\Controllers\Friends\ShowFriendsController;
use App\Http\Controllers\Home\ShowHomeController;
use App\Http\Controllers\Ranking\ShowRankingController;
use App\Http\Controllers\Trades\ShowTradesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::get('/', ShowHomeController::class)->name('home');
    Route::get('colecao', ShowCollectionController::class)->name('collection.show');
    Route::get('ranking', ShowRankingController::class)->name('ranking.show');
    Route::get('trocas', ShowTradesController::class)->name('trades.index');
    Route::get('amigos', ShowFriendsController::class)->name('friends.index');
});

if (! app()->isProduction()) {
    Route::inertia('/design-system', 'DesignSystem')->name('design-system');
}

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';

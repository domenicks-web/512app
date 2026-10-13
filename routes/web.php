<?php

declare(strict_types=1);

use App\Http\Controllers\Home\ShowHomeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::get('/', ShowHomeController::class)->name('home');
});

if (! app()->isProduction()) {
    Route::inertia('/design-system', 'DesignSystem')->name('design-system');
}

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarded'])->group(function () {
    Route::inertia('/', 'Welcome')->name('home');
});

if (! app()->isProduction()) {
    Route::inertia('/design-system', 'DesignSystem')->name('design-system');
}

require __DIR__.'/auth.php';

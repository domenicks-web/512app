<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\BirthdateUnlockController;
use App\Http\Controllers\Admin\InviteController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarded', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/convites')->name('index');

    Route::get('convites', [InviteController::class, 'index'])->name('invites.index');
    Route::post('convites', [InviteController::class, 'store'])->name('invites.store');
    Route::post('convites/{invite}/revogar', [InviteController::class, 'revoke'])->name('invites.revoke');

    Route::get('usuarios', [UserController::class, 'index'])->name('users.index');
    Route::post('usuarios/{user}/admin', [AdminRoleController::class, 'store'])->name('users.admin.store');
    Route::delete('usuarios/{user}/admin', [AdminRoleController::class, 'destroy'])->name('users.admin.destroy');
    Route::post('usuarios/{user}/liberar-nascimento', [BirthdateUnlockController::class, 'store'])->name('users.birthdate.unlock');
});

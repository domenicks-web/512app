<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\InviteController;
use App\Http\Controllers\Admin\InviteSuffixController;
use App\Http\Controllers\Admin\PlayerBirthdateController;
use App\Http\Controllers\Admin\PlayerController;
use App\Http\Controllers\Admin\PlayerPackController;
use App\Http\Controllers\Admin\PlayerPasswordController;
use App\Http\Controllers\Admin\PlayerQuotaController;
use App\Http\Controllers\Admin\PlayerRoleController;
use App\Http\Controllers\Admin\PlayerSanctionController;
use App\Http\Controllers\Admin\PlayerVerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'onboarded', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/convites')->name('index');

    Route::get('convites', [InviteController::class, 'index'])->name('invites.index');
    Route::get('convites/sufixo', InviteSuffixController::class)->name('invites.suffix');
    Route::post('convites', [InviteController::class, 'store'])->name('invites.store');
    Route::post('convites/{invite}/revogar', [InviteController::class, 'revoke'])->name('invites.revoke');

    Route::get('jogadores', [PlayerController::class, 'index'])->name('players.index');
    Route::patch('jogadores/{user}/cota', [PlayerQuotaController::class, 'update'])->name('players.quota');
    Route::patch('jogadores/{user}/papel', [PlayerRoleController::class, 'update'])->name('players.role');
    Route::post('jogadores/{user}/liberar-nascimento', [PlayerBirthdateController::class, 'store'])->name('players.birthdate');
    Route::post('jogadores/{user}/reenviar-confirmacao', [PlayerVerificationController::class, 'store'])->name('players.verification');
    Route::put('jogadores/{user}/senha', [PlayerPasswordController::class, 'update'])->name('players.password');
    Route::post('jogadores/{user}/liberar-pack', [PlayerPackController::class, 'store'])->name('players.pack');
    Route::post('jogadores/{user}/suspender', [PlayerSanctionController::class, 'suspend'])->name('players.suspend');
    Route::post('jogadores/{user}/banir', [PlayerSanctionController::class, 'ban'])->name('players.ban');
    Route::post('jogadores/{user}/encerrar-suspensao', [PlayerSanctionController::class, 'lift'])->name('players.lift');
});

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\UnlockBirthdateCorrection;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PlayerBirthdateController extends Controller
{
    public function store(User $user, UnlockBirthdateCorrection $action): RedirectResponse
    {
        $action->handle($user);

        Inertia::flash('toast', 'Correção da data liberada pra '.($user->nickname ?? $user->email));

        return back();
    }
}

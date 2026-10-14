<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ResendVerificationEmail;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PlayerVerificationController extends Controller
{
    public function store(User $user, ResendVerificationEmail $action): RedirectResponse
    {
        if (! $action->handle($user)) {
            return back()->withErrors(['verification' => 'Esse email já foi confirmado.']);
        }

        Inertia::flash('toast', "Confirmação reenviada pra {$user->email}");

        return back();
    }
}

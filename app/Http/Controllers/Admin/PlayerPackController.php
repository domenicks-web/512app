<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\GrantNextPack;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PlayerPackController extends Controller
{
    public function store(User $user, GrantNextPack $action): RedirectResponse
    {
        $action->handle($user);
        Inertia::flash('toast', 'Próximo pack liberado pra '.($user->nickname ?? $user->email));

        return back();
    }
}

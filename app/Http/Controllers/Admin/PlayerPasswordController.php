<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\SetPlayerPassword;
use App\Exceptions\AdminChangeNotAllowedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePlayerPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PlayerPasswordController extends Controller
{
    public function update(UpdatePlayerPasswordRequest $request, User $user, SetPlayerPassword $action): RedirectResponse
    {
        /** @var User $admin */
        $admin = Auth::user();

        try {
            $action->handle($admin, $user, $request->string('password')->toString());
        } catch (AdminChangeNotAllowedException $exception) {
            abort(403, $exception->getMessage());
        }

        Inertia::flash('toast', 'Senha de '.($user->nickname ?? $user->email).' trocada');

        return back();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\SetAdmin;
use App\Exceptions\AdminChangeNotAllowedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminRoleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PlayerRoleController extends Controller
{
    public function update(UpdateAdminRoleRequest $request, User $user, SetAdmin $action): RedirectResponse
    {
        /** @var User $admin */
        $admin = Auth::user();
        $isAdmin = $request->boolean('is_admin');

        try {
            $action->handle($admin, $user, $isAdmin);
        } catch (AdminChangeNotAllowedException $exception) {
            abort_if($exception->isSelfChange, 403, $exception->getMessage());

            return back()->withErrors(['role' => $exception->getMessage()]);
        }

        $name = $user->nickname ?? $user->email;

        Inertia::flash('toast', $isAdmin ? "{$name} agora é admin" : "{$name} voltou a ser jogador");

        return back();
    }
}

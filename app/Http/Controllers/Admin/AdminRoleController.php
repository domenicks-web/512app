<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\GrantAdmin;
use App\Actions\Admin\RevokeAdmin;
use App\Exceptions\AdminChangeNotAllowedException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class AdminRoleController extends Controller
{
    public function store(User $user, GrantAdmin $action): RedirectResponse
    {
        $action->handle($user);

        return redirect()->route('admin.users.index');
    }

    public function destroy(User $user, RevokeAdmin $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = Auth::user();

        try {
            $action->handle($actor, $user);
        } catch (AdminChangeNotAllowedException $exception) {
            return back()->withErrors(['admin' => $exception->getMessage()]);
        }

        return redirect()->route('admin.users.index');
    }
}

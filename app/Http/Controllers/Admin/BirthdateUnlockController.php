<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\UnlockBirthdateCorrection;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class BirthdateUnlockController extends Controller
{
    public function store(User $user, UnlockBirthdateCorrection $action): RedirectResponse
    {
        $action->handle($user);

        return redirect()->route('admin.users.index');
    }
}

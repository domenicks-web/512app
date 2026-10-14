<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\SetInviteQuota;
use App\Exceptions\AdminChangeNotAllowedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateInviteQuotaRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class PlayerQuotaController extends Controller
{
    public function update(UpdateInviteQuotaRequest $request, User $user, SetInviteQuota $action): RedirectResponse
    {
        try {
            $action->handle($user, $request->integer('quota'));
        } catch (AdminChangeNotAllowedException $exception) {
            return back()->withErrors(['quota' => $exception->getMessage()]);
        }

        return back();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CreateInvite;
use App\Actions\Admin\RevokeInvite;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInviteRequest;
use App\Http\Resources\Admin\InviteResource;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InviteController extends Controller
{
    public function index(): Response
    {
        $invites = Invite::with(['createdBy', 'redemptions.user'])->latest('id')->get();

        return Inertia::render('admin/Invites', [
            'invites' => InviteResource::collection($invites)->resolve(),
            'limits' => [
                'max_uses' => config('game.invites.max_uses_limit'),
                'expires_in_days' => config('game.invites.expires_in_days_limit'),
            ],
        ]);
    }

    public function store(StoreInviteRequest $request, CreateInvite $action): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $invite = $action->handle($user, $request->toCreateInviteData());

        return redirect()->route('admin.invites.index')->with('created_invite_code', $invite->code);
    }

    public function revoke(Invite $invite, RevokeInvite $action): RedirectResponse
    {
        $action->handle($invite);

        return redirect()->route('admin.invites.index');
    }
}

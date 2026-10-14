<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CountAdminTabs;
use App\Actions\Admin\CreateInvite;
use App\Actions\Admin\DefaultInvitePrefix;
use App\Actions\Admin\RevokeInvite;
use App\Exceptions\InviteNotRevocableException;
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
    public function index(CountAdminTabs $countTabs, DefaultInvitePrefix $defaultPrefix): Response
    {
        /** @var User $admin */
        $admin = Auth::user();
        $invites = Invite::with(['createdBy:id,nickname,email', 'redemptions.user.avatarSpecies:id,sprite_path'])->latest('id')->get();

        return Inertia::render('admin/Invites', [
            'invites' => InviteResource::collection($invites)->resolve(),
            'counts' => $countTabs->handle(),
            'form' => [
                'defaultPrefix' => $defaultPrefix->handle($admin),
                'prefixMaxLength' => config('game.invites.code_prefix_max_length'),
                'alphabet' => config('game.invites.code_alphabet'),
                'suffixLength' => config('game.invites.code_suffix_length'),
                'maxUses' => config('game.invites.max_uses'),
                'validities' => array_map('strval', array_keys(config('game.invites.validity_days'))),
                'defaultValidity' => config('game.invites.default_validity'),
            ],
        ]);
    }

    public function store(StoreInviteRequest $request, CreateInvite $action): RedirectResponse
    {
        /** @var User $admin */
        $admin = Auth::user();

        $invite = $action->handle($admin, $request->toCreateInviteData());

        Inertia::flash(['toast' => "{$invite->code} criado", 'createdInviteId' => $invite->id]);

        return back();
    }

    public function revoke(Invite $invite, RevokeInvite $action): RedirectResponse
    {
        /** @var User $admin */
        $admin = Auth::user();

        try {
            $action->handle($admin, $invite);
        } catch (InviteNotRevocableException $exception) {
            return back()->withErrors(['invite' => $exception->getMessage()]);
        }

        Inertia::flash('toast', "{$invite->code} revogado");

        return back();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Friends;

use App\Actions\Friends\BuildFriendList;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ShowFriendsController extends Controller
{
    public function __invoke(BuildFriendList $action): Response
    {
        /** @var User $user */
        $user = Auth::user();

        return Inertia::render('friends/Index', [
            ...$action->handle($user, now()),
            'presencePollSeconds' => (int) config('game.group.presence_poll_seconds'),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Friends;

use App\Actions\BuildGroupRoster;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ShowFriendsController extends Controller
{
    public function __invoke(BuildGroupRoster $action): Response
    {
        /** @var User $user */
        $user = Auth::user();

        return Inertia::render('friends/Index', ['players' => $action->handle($user, now())]);
    }
}

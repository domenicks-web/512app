<?php

declare(strict_types=1);

namespace App\Http\Controllers\Friends;

use App\Actions\Friends\SendFriendRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendFriendRequestRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class SendFriendRequestController extends Controller
{
    public function __invoke(SendFriendRequestRequest $request, SendFriendRequest $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->handle($user, $request->friendHandle());

        return back();
    }
}

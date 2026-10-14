<?php

declare(strict_types=1);

namespace App\Http\Controllers\Friends;

use App\Actions\Friends\AcceptFriendRequest;
use App\Http\Controllers\Controller;
use App\Models\Friendship;
use Illuminate\Http\RedirectResponse;

class AcceptFriendRequestController extends Controller
{
    public function __invoke(Friendship $friendship, AcceptFriendRequest $action): RedirectResponse
    {
        $action->handle($friendship);

        return back();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Friends;

use App\Actions\Friends\RemoveFriendship;
use App\Http\Controllers\Controller;
use App\Models\Friendship;
use Illuminate\Http\RedirectResponse;

class RemoveFriendshipController extends Controller
{
    public function __invoke(Friendship $friendship, RemoveFriendship $action): RedirectResponse
    {
        $action->handle($friendship);

        return back();
    }
}

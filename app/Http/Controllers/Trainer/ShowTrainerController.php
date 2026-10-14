<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trainer;

use App\Actions\Trainer\BuildTrainerCard;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ShowTrainerController extends Controller
{
    public function __invoke(User $user, BuildTrainerCard $action): Response
    {
        abort_unless($user->hasCompletedProfile(), 404);

        /** @var User $viewer */
        $viewer = Auth::user();

        return Inertia::render('trainer/Show', $action->handle($user, $viewer));
    }
}

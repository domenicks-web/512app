<?php

declare(strict_types=1);

namespace App\Http\Controllers\Profile;

use App\Actions\Profile\BuildProfileForm;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ShowProfileController extends Controller
{
    public function __invoke(BuildProfileForm $action): Response
    {
        /** @var User $user */
        $user = Auth::user();

        return Inertia::render('profile/Edit', $action->handle($user));
    }
}

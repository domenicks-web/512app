<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Species;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ShowOnboardingController extends Controller
{
    public function __invoke(): Response|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->hasCompletedProfile()) {
            return redirect()->route('home');
        }

        $avatarOptions = Species::whereIn('id', config('game.onboarding.avatar_species_ids'))
            ->get(['id', 'name', 'artwork_path']);

        return Inertia::render('onboarding/Show', [
            'avatarOptions' => $avatarOptions,
        ]);
    }
}

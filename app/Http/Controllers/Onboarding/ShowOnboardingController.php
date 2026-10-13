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

        $avatarSpeciesIds = config('game.onboarding.avatar_species_ids');

        // Mantém a ordem curada do config (Pikachu primeiro, etc.) em vez da
        // ordem crua do banco por id — é a ordem da grade de parceiros.
        $avatarOptions = Species::whereIn('id', $avatarSpeciesIds)
            ->get(['id', 'name', 'sprite_path'])
            ->sortBy(fn (Species $species): int => array_search($species->id, $avatarSpeciesIds, true))
            ->values();

        return Inertia::render('onboarding/Show', [
            'avatarOptions' => $avatarOptions,
            'nicknameMaxLength' => config('game.onboarding.nickname_max_length'),
            'birthYearRange' => [config('game.onboarding.birth_year_min'), now()->year],
        ]);
    }
}

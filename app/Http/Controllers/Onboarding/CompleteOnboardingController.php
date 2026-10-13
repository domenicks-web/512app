<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Actions\CompleteOnboarding;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteOnboardingRequest;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class CompleteOnboardingController extends Controller
{
    /**
     * 204 nos dois casos: o cliente anima a saída do wizard e navega pra
     * home, onde o middleware `onboarded` decide o resto.
     */
    public function store(CompleteOnboardingRequest $request, CompleteOnboarding $action): Response
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasCompletedProfile()) {
            $action->handle($user, $request->toCompleteOnboardingData());
            $request->session()->forget(PreviewUserTagController::SESSION_KEY);
        }

        return response()->noContent();
    }
}

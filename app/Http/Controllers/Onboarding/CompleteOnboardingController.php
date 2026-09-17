<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Actions\CompleteOnboarding;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteOnboardingRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class CompleteOnboardingController extends Controller
{
    public function store(CompleteOnboardingRequest $request, CompleteOnboarding $action): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $action->handle($user, $request->toCompleteOnboardingData());

        return redirect()->route('home');
    }
}

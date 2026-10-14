<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            Inertia::flash('toast', 'Esse email já estava confirmado.');

            return redirect()->route('home');
        }

        $request->user()->markEmailAsVerified();
        event(new Verified($request->user()));
        Inertia::flash('toast', 'Email confirmado!');

        return redirect()->route('home');
    }
}

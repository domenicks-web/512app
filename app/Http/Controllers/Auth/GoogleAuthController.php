<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\LoginWithGoogle;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(LoginWithGoogle $action): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        Auth::login($action->handle($googleUser));

        return redirect()->route('home');
    }
}

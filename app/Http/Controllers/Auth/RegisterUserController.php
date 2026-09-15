<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\RegisterUser;
use App\Exceptions\InviteNotRedeemableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterUserRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class RegisterUserController extends Controller
{
    public function store(RegisterUserRequest $request, RegisterUser $action): RedirectResponse
    {
        try {
            $user = $action->handle($request->toRegisterUserData());
        } catch (InviteNotRedeemableException $exception) {
            return back()->withErrors(['invite_code' => $exception->getMessage()])->withInput();
        }

        Auth::login($user);

        return redirect()->route('home');
    }
}

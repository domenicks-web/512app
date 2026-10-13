<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\RegisterUser;
use App\Exceptions\InviteNotRedeemableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterUserRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RegisterUserController extends Controller
{
    public function store(RegisterUserRequest $request, RegisterUser $action): Response
    {
        try {
            $user = $action->handle($request->toRegisterUserData());
        } catch (InviteNotRedeemableException $exception) {
            $request->session()->forget(ValidateInviteController::SESSION_KEY);

            throw ValidationException::withMessages(['invite_code' => $exception->getMessage()]);
        }

        $request->session()->forget(ValidateInviteController::SESSION_KEY);
        Auth::login($user);
        $request->session()->regenerate();

        return response()->noContent();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SendPasswordResetLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendPasswordResetLinkRequest;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * "Esqueci a senha": pede o email e manda o link. Responde 204 sempre,
 * tenha conta ou não, pra não entregar quem está cadastrado.
 */
class ForgotPasswordController extends Controller
{
    public function show(): InertiaResponse
    {
        return Inertia::render('auth/ForgotPassword', [
            'linkMinutes' => (int) config('auth.passwords.users.expire'),
        ]);
    }

    public function store(SendPasswordResetLinkRequest $request, SendPasswordResetLink $action): Response
    {
        $action->handle($request->email());

        return response()->noContent();
    }
}

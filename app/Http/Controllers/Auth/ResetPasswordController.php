<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResetForgottenPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tela da senha nova. O link do email traz token e email; eles vão pra
 * sessão e a tela abre numa URL limpa (o token não fica no histórico nem
 * vaza em Referer). A tela só pede a senha nova; a troca responde JSON com
 * o email pro cliente mostrar o popup e levar pro login.
 */
class ResetPasswordController extends Controller
{
    public const SESSION_KEY = 'password_reset';

    public function capture(Request $request, string $token): RedirectResponse
    {
        $request->session()->put(self::SESSION_KEY, [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);

        return redirect()->route('password.reset');
    }

    public function show(Request $request): Response
    {
        return Inertia::render('auth/ResetPassword', [
            'isLinkValid' => $this->pendingReset($request) !== null,
        ]);
    }

    public function store(ResetPasswordRequest $request, ResetForgottenPassword $action): JsonResponse
    {
        $pending = $this->pendingReset($request);

        if ($pending === null || ! $action->handle($pending['email'], $pending['token'], $request->newPassword())) {
            $request->session()->forget(self::SESSION_KEY);

            throw ValidationException::withMessages(['token' => __('passwords.token')]);
        }

        $request->session()->forget(self::SESSION_KEY);

        return response()->json(['email' => $pending['email']]);
    }

    /**
     * Token e email guardados na sessão, só se o token ainda vale.
     *
     * @return array{token: string, email: string}|null
     */
    private function pendingReset(Request $request): ?array
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! is_array($pending) || ! is_string($pending['token'] ?? null) || ! is_string($pending['email'] ?? null)) {
            return null;
        }

        $user = User::query()->where('email', $pending['email'])->first();

        if ($user === null || ! Password::tokenExists($user, $pending['token'])) {
            return null;
        }

        return ['token' => $pending['token'], 'email' => $pending['email']];
    }
}

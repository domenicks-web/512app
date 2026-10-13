<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidateInviteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Primeira etapa do cadastro: o convite é conferido aqui e guardado na
 * sessão, e só então `/cadastro` libera o formulário de email e senha.
 */
class ValidateInviteController extends Controller
{
    public const SESSION_KEY = 'register.invite_code';

    public function store(ValidateInviteRequest $request): JsonResponse
    {
        $request->session()->put(self::SESSION_KEY, $request->inviteCode());

        return response()->json(['inviteCode' => $request->inviteCode()]);
    }

    public function destroy(Request $request): Response
    {
        $request->session()->forget(self::SESSION_KEY);

        return response()->noContent();
    }
}

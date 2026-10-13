<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginUserRequest;
use Illuminate\Http\Response;

/**
 * Responde 204 em vez de redirecionar: o cliente roda a animação da
 * pokébola enquanto espera e só navega pra home quando ela termina.
 */
class LoginUserController extends Controller
{
    public function store(LoginUserRequest $request): Response
    {
        $request->authenticate();

        $request->session()->regenerate();

        return response()->noContent();
    }
}

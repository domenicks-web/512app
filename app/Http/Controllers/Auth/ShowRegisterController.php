<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invite;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowRegisterController extends Controller
{
    /**
     * Sem convite válido na sessão, a página só mostra a porta fechada. O
     * convite é reconferido a cada visita: pode ter sido revogado ou esgotado
     * entre a validação e o cadastro.
     */
    public function __invoke(Request $request): Response
    {
        $inviteCode = $request->session()->get(ValidateInviteController::SESSION_KEY);

        if ($inviteCode !== null && ! Invite::where('code', $inviteCode)->first()?->isRedeemable()) {
            $request->session()->forget(ValidateInviteController::SESSION_KEY);
            $inviteCode = null;
        }

        return Inertia::render('auth/Register', [
            'inviteCode' => $inviteCode,
        ]);
    }
}

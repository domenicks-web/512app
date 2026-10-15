<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quem foi suspenso ou banido com o app aberto cai no próximo request:
 * sai da conta e vê o motivo no login (flash `sanction`).
 */
class EnsureNotSanctioned
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        $message = $user === null ? null : ($user->is_system
            ? 'Essa conta é do sistema.'
            : $user->activeSanction()?->message());

        if ($message === null) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('sanction', $message);
    }
}

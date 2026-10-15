<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\BanPlayer;
use App\Actions\Admin\LiftSuspension;
use App\Actions\Admin\SuspendPlayer;
use App\Exceptions\SanctionNotAllowedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BanPlayerRequest;
use App\Http\Requests\Admin\SuspendPlayerRequest;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PlayerSanctionController extends Controller
{
    public function suspend(SuspendPlayerRequest $request, User $user, SuspendPlayer $action): RedirectResponse
    {
        $days = $request->integer('days');

        return $this->run(
            fn (User $admin) => $action->handle($admin, $user, $days, $request->string('reason')->trim()->toString()),
            "{$this->name($user)} suspenso por {$days} ".($days === 1 ? 'dia' : 'dias'),
        );
    }

    public function ban(BanPlayerRequest $request, User $user, BanPlayer $action): RedirectResponse
    {
        return $this->run(
            fn (User $admin) => $action->handle($admin, $user, $request->string('reason')->trim()->toString()),
            "{$this->name($user)} banido",
        );
    }

    public function lift(User $user, LiftSuspension $action): RedirectResponse
    {
        return $this->run(
            fn (User $admin) => $action->handle($admin, $user),
            "Suspensão de {$this->name($user)} encerrada",
        );
    }

    /**
     * @param  Closure(User): mixed  $apply
     */
    private function run(Closure $apply, string $toast): RedirectResponse
    {
        /** @var User $admin */
        $admin = Auth::user();

        try {
            $apply($admin);
        } catch (SanctionNotAllowedException $exception) {
            return back()->withErrors(['sanction' => $exception->getMessage()]);
        }

        Inertia::flash('toast', $toast);

        return back();
    }

    private function name(User $user): string
    {
        return $user->nickname ?? $user->email;
    }
}

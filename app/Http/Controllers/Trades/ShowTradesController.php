<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trades;

use App\Actions\BuildTradeInbox;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ShowTradesController extends Controller
{
    public function __invoke(BuildTradeInbox $action): Response
    {
        /** @var User $user */
        $user = Auth::user();

        return Inertia::render('trades/Index', $action->handle($user));
    }
}

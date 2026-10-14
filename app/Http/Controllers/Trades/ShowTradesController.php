<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trades;

use App\Actions\Trades\BuildTradeInbox;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowTradesController extends Controller
{
    public function __invoke(Request $request, BuildTradeInbox $action): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('trades/Index', $action->handle($user, $request));
    }
}

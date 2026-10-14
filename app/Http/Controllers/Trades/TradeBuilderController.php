<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trades;

use App\Actions\Trades\BuildTradeBuilder;
use App\Http\Controllers\Controller;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TradeBuilderController extends Controller
{
    public function create(Request $request, User $friend, BuildTradeBuilder $action): Response
    {
        $viewer = $this->viewer($request);
        abort_unless(in_array($friend->id, $viewer->friendIds(), true), 404);

        return Inertia::render('trades/Create', $action->handle($viewer, $friend, $request));
    }

    public function counter(Request $request, Trade $trade, BuildTradeBuilder $action): Response
    {
        $viewer = $this->viewer($request);
        abort_unless($trade->to_user_id === $viewer->id && $trade->isOpen(), 404);

        return Inertia::render('trades/Create', $action->handle($viewer, User::findOrFail($trade->from_user_id), $request, $trade));
    }

    private function viewer(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}

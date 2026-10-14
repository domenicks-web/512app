<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trades;

use App\Actions\Trades\AcceptTrade;
use App\Actions\Trades\CancelTrade;
use App\Actions\Trades\CounterTrade;
use App\Actions\Trades\DeclineTrade;
use App\Actions\Trades\MarkTradeSeen;
use App\Actions\Trades\ProposeTrade;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProposeTradeRequest;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TradeController extends Controller
{
    public function store(ProposeTradeRequest $request, ProposeTrade $action): RedirectResponse
    {
        $action->handle($this->user($request), User::findOrFail($request->integer('friend_id')), $request->offeredIds(), $request->requestedIds(), $request->message());

        return to_route('trades.index', ['aba' => 'enviadas']);
    }

    public function counter(ProposeTradeRequest $request, Trade $trade, CounterTrade $action): RedirectResponse
    {
        $action->handle($trade, $this->user($request), $request->offeredIds(), $request->requestedIds(), $request->message());

        return to_route('trades.index', ['aba' => 'enviadas']);
    }

    public function accept(Request $request, Trade $trade, AcceptTrade $action): RedirectResponse
    {
        $action->handle($trade, $this->user($request));

        return to_route('trades.index', ['troca' => $trade->id]);
    }

    public function decline(Request $request, Trade $trade, DeclineTrade $action): RedirectResponse
    {
        $action->handle($trade, $this->user($request));

        return back();
    }

    public function cancel(Request $request, Trade $trade, CancelTrade $action): RedirectResponse
    {
        $action->handle($trade, $this->user($request));

        return back();
    }

    public function seen(Request $request, Trade $trade, MarkTradeSeen $action): RedirectResponse
    {
        $action->handle($trade, $this->user($request));

        return back();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}

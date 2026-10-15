<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trades;

use App\Actions\Trades\BuildTradeInbox;
use App\Actions\Trades\MarkTradeOutcomesSeen;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowTradesController extends Controller
{
    public function __invoke(Request $request, BuildTradeInbox $action, MarkTradeOutcomesSeen $markSeen): Response
    {
        /** @var User $user */
        $user = $request->user();
        // Monta antes de marcar: o que era novo ainda chega com o selo "Nova".
        $inbox = $action->handle($user, $request);
        $markSeen->handle($user, $inbox['tab']);

        return Inertia::render('trades/Index', $inbox);
    }
}

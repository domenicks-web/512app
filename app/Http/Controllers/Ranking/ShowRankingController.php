<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ranking;

use App\Actions\Ranking\BuildRanking;
use App\Enums\RankingTab;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ShowRankingController extends Controller
{
    public function __invoke(Request $request, BuildRanking $action): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $tab = RankingTab::tryFrom($request->string('por')->toString()) ?? RankingTab::Species;

        return Inertia::render('ranking/Show', ['rows' => $action->handle($tab), 'viewerId' => $user->id, 'tab' => $tab->value]);
    }
}

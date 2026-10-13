<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ranking;

use App\Actions\BuildSpeciesRanking;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ShowRankingController extends Controller
{
    public function __invoke(BuildSpeciesRanking $action): Response
    {
        /** @var User $user */
        $user = Auth::user();

        return Inertia::render('ranking/Show', ['rows' => $action->handle(), 'viewerId' => $user->id]);
    }
}

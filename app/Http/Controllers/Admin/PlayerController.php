<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CountAdminTabs;
use App\Actions\Admin\SearchPlayers;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PlayerResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlayerController extends Controller
{
    public function index(Request $request, SearchPlayers $searchPlayers, CountAdminTabs $countTabs): Response
    {
        $search = $request->string('q')->trim()->toString();

        return Inertia::render('admin/Players', [
            'players' => PlayerResource::collection($searchPlayers->handle($search))->resolve(),
            'search' => $search,
            'counts' => $countTabs->handle(),
            'quota' => config('game.invites.quota'),
            'sanctionDays' => config('game.sanctions.suspension_days'),
        ]);
    }
}

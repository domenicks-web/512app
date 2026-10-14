<?php

declare(strict_types=1);

namespace App\Http\Controllers\Feed;

use App\Actions\BuildGroupFeed;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ShowFeedController extends Controller
{
    public function __invoke(BuildGroupFeed $action): Response
    {
        return Inertia::render('feed/Show', ['entries' => $action->handle((int) config('game.group.feed_limit'))]);
    }
}

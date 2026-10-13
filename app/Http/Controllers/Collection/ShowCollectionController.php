<?php

declare(strict_types=1);

namespace App\Http\Controllers\Collection;

use App\Actions\BuildCollection;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ShowCollectionController extends Controller
{
    public function __invoke(BuildCollection $action): Response
    {
        /** @var User $user */
        $user = Auth::user();

        return Inertia::render('collection/Show', $action->handle($user));
    }
}

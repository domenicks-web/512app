<?php

declare(strict_types=1);

namespace App\Http\Controllers\Home;

use App\Actions\BuildHomeSummary;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ShowHomeController extends Controller
{
    public function __invoke(BuildHomeSummary $action): Response
    {
        /** @var User $user */
        $user = Auth::user();

        return Inertia::render('Home', $action->handle($user, now()));
    }
}

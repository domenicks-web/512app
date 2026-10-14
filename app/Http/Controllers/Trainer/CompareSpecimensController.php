<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trainer;

use App\Actions\Trainer\BuildComparison;
use App\Http\Controllers\Controller;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CompareSpecimensController extends Controller
{
    public function __invoke(Request $request, Specimen $mine, Specimen $theirs, BuildComparison $action): Response
    {
        /** @var User $viewer */
        $viewer = Auth::user();

        return Inertia::render('compare/Show', $action->handle($viewer, $mine, $theirs, $request));
    }
}

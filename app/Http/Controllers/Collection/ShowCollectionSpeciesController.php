<?php

declare(strict_types=1);

namespace App\Http\Controllers\Collection;

use App\Actions\BuildSpeciesSpecimens;
use App\Http\Controllers\Controller;
use App\Models\Species;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Ficha da coleção: os espécimes do jogador daquela espécie (JSON). */
class ShowCollectionSpeciesController extends Controller
{
    public function __invoke(Request $request, Species $species, BuildSpeciesSpecimens $action): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        return response()->json(['specimens' => $action->handle($user, $species, $request)]);
    }
}

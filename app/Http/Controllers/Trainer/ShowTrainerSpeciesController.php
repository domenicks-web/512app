<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trainer;

use App\Actions\BuildSpeciesSpecimens;
use App\Http\Controllers\Controller;
use App\Models\Species;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Ficha no Cartão de Treinador: os espécimes do dono daquela espécie e o
 * melhor de quem vê, pro "Comparar com o meu" (JSON).
 */
class ShowTrainerSpeciesController extends Controller
{
    public function __invoke(Request $request, User $user, Species $species, BuildSpeciesSpecimens $action): JsonResponse
    {
        abort_unless($user->hasCompletedProfile(), 404);

        /** @var User $viewer */
        $viewer = Auth::user();

        return response()->json([
            'specimens' => $action->handle($user, $species, $request),
            'viewerSpecimenId' => $action->handle($viewer, $species, $request)[0]['id'] ?? null,
        ]);
    }
}

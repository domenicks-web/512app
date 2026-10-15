<?php

declare(strict_types=1);

namespace App\Http\Controllers\Specimen;

use App\Actions\PresentSpecimenSheet;
use App\Http\Controllers\Controller;
use App\Models\Specimen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ficha de um espécime só, pelas trocas (JSON no mesmo formato da ficha da
 * coleção). Vale pra qualquer espécime revelado: depois de uma troca o
 * dono já mudou, então não dá pra buscar pela coleção de ninguém. Bola
 * fechada nem chega aqui (o `RevealedScope` dá 404).
 */
class ShowSpecimenController extends Controller
{
    public function __invoke(Request $request, Specimen $specimen, PresentSpecimenSheet $action): JsonResponse
    {
        return response()->json([
            'specimens' => [$action->handle($specimen->load(PresentSpecimenSheet::RELATIONS), $request)],
        ]);
    }
}

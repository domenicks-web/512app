<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pack;

use App\Actions\Pack\BuildPackScreen;
use App\Actions\Pack\OpenPack;
use App\Actions\Pack\RevealSlot;
use App\Exceptions\PackOnCooldownException;
use App\Http\Controllers\Controller;
use App\Http\Resources\PackSpecimenResource;
use App\Models\Opening;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PackController extends Controller
{
    public function show(Request $request, BuildPackScreen $screen): Response
    {
        /** @var User $user */
        $user = Auth::user();

        return Inertia::render('pack/Show', $screen->handle($user, $request));
    }

    /** A explosão: sorteia e grava, mas só devolve quantas bolas caíram. */
    public function open(OpenPack $openPack): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        try {
            $opening = $openPack->handle($user);
        } catch (PackOnCooldownException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'availableAt' => $exception->availableAt->toIso8601String(),
            ], 409);
        }

        return response()->json([
            'openingId' => $opening->id,
            'slots' => (int) config('game.pack.slots'),
            'serverSeedHash' => $user->next_server_seed_hash,
            'nextPackAt' => $user->next_pack_at?->toIso8601String(),
        ]);
    }

    /** Uma bola clicada: só agora o conteúdo dela sai do servidor. */
    public function reveal(Request $request, Opening $opening, int $slot, RevealSlot $revealSlot, BuildPackScreen $screen): JsonResponse
    {
        abort_unless($opening->user_id === Auth::id(), 404);
        abort_unless($slot >= 1 && $slot <= (int) config('game.pack.slots'), 404);

        $specimen = $revealSlot->handle($opening, $slot);
        $specimen->load('species');

        return response()->json([
            'specimen' => (new PackSpecimenResource($specimen))->toArray($request),
            'isComplete' => $opening->isComplete(),
            'receipt' => $screen->opening($opening, $request)['receipt'],
        ]);
    }
}

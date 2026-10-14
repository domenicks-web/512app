<?php

declare(strict_types=1);

namespace App\Http\Controllers\Verify;

use App\Actions\Pack\VerifyPack;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyPackRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerifyPackController extends Controller
{
    /** A tela, já preenchida quando vem do comprovante. */
    public function show(Request $request): Response
    {
        return Inertia::render('verify/Show', [
            'prefill' => [
                'serverSeed' => $request->string('server_seed')->toString(),
                'serverSeedHash' => $request->string('server_seed_hash')->toString(),
                'clientSeed' => $request->string('client_seed')->toString(),
                'nonce' => $request->string('nonce')->toString(),
            ],
        ]);
    }

    public function store(VerifyPackRequest $request, VerifyPack $verifyPack): JsonResponse
    {
        $hash = $request->string('server_seed_hash')->toString();

        return response()->json($verifyPack->handle(
            $request->string('server_seed')->toString(),
            $request->string('client_seed')->toString(),
            $request->integer('nonce'),
            $hash === '' ? null : $hash,
        ));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pack;

use App\Actions\Pack\UpdateClientSeed;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateClientSeedRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ClientSeedController extends Controller
{
    public function update(UpdateClientSeedRequest $request, UpdateClientSeed $action): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $action->handle($user, $request->string('client_seed')->toString());

        return response()->json(['clientSeed' => $user->client_seed]);
    }
}

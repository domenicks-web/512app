<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\GenerateInviteSuffix;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInviteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sorteia o sufixo da prévia do gerador e guarda na sessão: o POST de
 * criação grava esse mesmo sufixo (se ainda estiver livre).
 */
class InviteSuffixController extends Controller
{
    public function __invoke(Request $request, GenerateInviteSuffix $generateSuffix): JsonResponse
    {
        $suffix = $generateSuffix->handle();
        $request->session()->put(StoreInviteRequest::SUFFIX_SESSION_KEY, $suffix);

        return response()->json(['suffix' => $suffix]);
    }
}

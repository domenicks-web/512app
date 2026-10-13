<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Actions\GenerateUserTag;
use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewUserTagRequest;
use Illuminate\Http\JsonResponse;

/**
 * Sorteia no servidor a tag que o cartão de treinador mostra enquanto o
 * jogador digita o nickname. Fica guardada na sessão pra que o
 * `CompleteOnboarding` grave exatamente essa (se o par ainda estiver livre)
 * sem confiar em tag nenhuma vinda do cliente.
 */
class PreviewUserTagController extends Controller
{
    public const SESSION_KEY = 'onboarding.tag_preview';

    public function __invoke(PreviewUserTagRequest $request, GenerateUserTag $generateUserTag): JsonResponse
    {
        $tag = $generateUserTag->handle($request->nickname());

        $request->session()->put(self::SESSION_KEY, ['nickname' => $request->nickname(), 'tag' => $tag]);

        return response()->json(['tag' => $tag]);
    }
}

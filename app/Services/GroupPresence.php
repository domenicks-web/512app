<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Quem está online no grupo, sem realtime: lê a última atividade gravada
 * na tabela `sessions` (só funciona com `SESSION_DRIVER=database`; com
 * outro driver ninguém aparece online). A janela vem de
 * `game.group.online_window_minutes`.
 */
class GroupPresence
{
    /**
     * @return list<int>
     */
    public function onlineUserIds(CarbonInterface $now): array
    {
        $since = $now->copy()->subMinutes((int) config('game.group.online_window_minutes'));

        return DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $since->getTimestamp())
            ->distinct()
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }
}

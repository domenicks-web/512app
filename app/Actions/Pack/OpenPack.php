<?php

declare(strict_types=1);

namespace App\Actions\Pack;

use App\Actions\DrawPack;
use App\Actions\ResolveSpecimen;
use App\Enums\RarityTier;
use App\Exceptions\PackOnCooldownException;
use App\Models\Opening;
use App\Models\Species;
use App\Models\Specimen;
use App\Models\User;
use App\Services\ProvablyFair;
use Illuminate\Support\Facades\DB;

/**
 * Abre o pack do dia: sorteia os 5 slots com a seed comprometida, grava a
 * abertura e os espécimes (ainda fechados, `revealed_at` nulo), incrementa
 * o nonce, liga o cooldown e já compromete a seed do próximo pack.
 *
 * Tudo numa transação: o usuário é travado (dois cliques não abrem dois
 * packs) e as espécies sorteadas também, pra duas aberturas simultâneas
 * nunca pegarem o mesmo mint.
 */
class OpenPack
{
    public function __construct(
        private readonly DrawPack $drawPack = new DrawPack,
        private readonly ResolveSpecimen $resolveSpecimen = new ResolveSpecimen,
        private readonly CommitServerSeed $commitServerSeed = new CommitServerSeed,
        private readonly ProvablyFair $provablyFair = new ProvablyFair,
    ) {}

    public function handle(User $user): Opening
    {
        return DB::transaction(function () use ($user): Opening {
            /** @var User $locked */
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($locked->next_pack_at !== null && $locked->next_pack_at->isFuture()) {
                throw new PackOnCooldownException($locked->next_pack_at);
            }

            $this->commitServerSeed->handle($locked);
            $serverSeed = (string) $locked->next_server_seed;
            $draws = $this->drawPack->handle($serverSeed, $locked->client_seed, $locked->nonce, $this->speciesIdsByTier());

            $speciesIds = array_values(array_unique(array_map(fn ($draw): int => $draw->speciesId, $draws)));
            $species = Species::whereKey($speciesIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $nextMint = Specimen::withoutGlobalScopes()
                ->whereIn('species_id', $speciesIds)
                ->groupBy('species_id')
                ->selectRaw('species_id, max(mint_number) as last_mint')
                ->pluck('last_mint', 'species_id')
                ->map(fn ($mint): int => (int) $mint + 1)
                ->all();

            $opening = Opening::create([
                'user_id' => $locked->id,
                'server_seed' => $serverSeed,
                'server_seed_hash' => (string) $locked->next_server_seed_hash,
                'client_seed' => $locked->client_seed,
                'nonce' => $locked->nonce,
                'opened_slots' => 0,
                'revealed_at' => null,
            ]);

            $caughtAt = now();

            foreach ($draws as $draw) {
                $mint = $nextMint[$draw->speciesId] ?? 1;
                $nextMint[$draw->speciesId] = $mint + 1;
                $data = $this->resolveSpecimen->handle($draw->seed, $species[$draw->speciesId], $mint);

                Specimen::create([
                    'opening_id' => $opening->id,
                    'slot' => $draw->slot,
                    'user_id' => $locked->id,
                    'species_id' => $data->speciesId,
                    'seed' => $data->seed,
                    'is_shiny' => $data->isShiny,
                    'size_roll' => $data->sizeRoll,
                    'size_class' => $data->sizeClass,
                    'nature' => $data->nature,
                    'iv_hp' => $data->ivHp,
                    'iv_atk' => $data->ivAtk,
                    'iv_def' => $data->ivDef,
                    'iv_spa' => $data->ivSpa,
                    'iv_spd' => $data->ivSpd,
                    'iv_spe' => $data->ivSpe,
                    'mint_number' => $mint,
                    'hash' => $this->provablyFair->specimenHash($data->speciesId, $data->seed, $mint, $caughtAt->toIso8601String()),
                    'caught_at' => $caughtAt,
                    'revealed_at' => null,
                ]);
            }

            $locked->forceFill([
                'nonce' => $locked->nonce + 1,
                'next_pack_at' => now()->addHours((int) config('game.pack.cooldown_hours')),
            ])->save();
            $this->commitServerSeed->handle($locked, force: true);

            $user->refresh();

            return $opening;
        });
    }

    /**
     * @return array<string, list<int>>
     */
    private function speciesIdsByTier(): array
    {
        $byTier = [];

        foreach (RarityTier::cases() as $tier) {
            $byTier[$tier->value] = Species::where('rarity_tier', $tier->value)->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
            $byTier[$tier->value] = array_values($byTier[$tier->value]);
        }

        return $byTier;
    }
}

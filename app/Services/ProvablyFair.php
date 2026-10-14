<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Commit e reveal do sorteio (SPEC, seção 6). A server seed é gerada antes
 * e só o hash dela é mostrado; depois do pack aberto ela é revelada e
 * qualquer um refaz a conta.
 */
class ProvablyFair
{
    public function generateServerSeed(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hash(string $serverSeed): string
    {
        return hash('sha256', $serverSeed);
    }

    public function verify(string $serverSeed, string $serverSeedHash): bool
    {
        return hash_equals($serverSeedHash, $this->hash($serverSeed));
    }

    /** Seed de um slot: `HMAC-SHA256(server_seed, "{client_seed}:{nonce}:slot:{n}")`, em hex. */
    public function slotSeed(string $serverSeed, string $clientSeed, int $nonce, int $slot): string
    {
        return hash_hmac('sha256', "{$clientSeed}:{$nonce}:slot:{$slot}", $serverSeed);
    }

    /** Hash de identidade do espécime (SPEC, "Hash de verificação do espécime"). */
    public function specimenHash(int $speciesId, string $seed, int $mintNumber, string $caughtAt): string
    {
        return hash_hmac('sha256', "{$speciesId}|{$seed}|{$mintNumber}|{$caughtAt}", (string) config('game.specimen_secret'));
    }
}

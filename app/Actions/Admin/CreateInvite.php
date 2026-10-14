<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Actions\Data\CreateInviteData;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Grava "PREFIXO-SUFIXO". Tenta primeiro o sufixo da prévia (o que o admin
 * viu e talvez já copiou); se o código já existe, sorteia outro. A colisão
 * entre a checagem e o insert cai no índice único e tenta de novo.
 */
class CreateInvite
{
    public function __construct(private readonly GenerateInviteSuffix $generateSuffix = new GenerateInviteSuffix) {}

    public function handle(User $creator, CreateInviteData $data): Invite
    {
        $suffix = $data->suffix ?? $this->generateSuffix->handle();

        while (true) {
            $code = "{$data->prefix}-{$suffix}";

            if (! Invite::where('code', $code)->exists()) {
                try {
                    return Invite::create([
                        'code' => $code,
                        'created_by' => $creator->id,
                        'max_uses' => $data->maxUses,
                        'expires_at' => $data->expiresAt,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    // Outro admin gravou o mesmo código no meio do caminho.
                }
            }

            $suffix = $this->generateSuffix->handle();
        }
    }
}

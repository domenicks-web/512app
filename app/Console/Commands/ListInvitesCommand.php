<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InviteStatus;
use App\Models\Invite;
use Illuminate\Console\Command;

class ListInvitesCommand extends Command
{
    protected $signature = 'invite:list {--status= : ativo, esgotado, expirado ou revogado}';

    protected $description = 'Lista os convites com criador, usos, validade e status';

    public function handle(): int
    {
        $status = $this->option('status');
        $filter = $status !== null ? InviteStatus::fromLabel((string) $status) : null;

        if ($status !== null && $filter === null) {
            $this->error('--status aceita ativo, esgotado, expirado ou revogado.');

            return self::FAILURE;
        }

        $rows = Invite::with('createdBy:id,nickname,email')
            ->latest('id')
            ->get()
            ->filter(fn (Invite $invite): bool => $filter === null || $invite->status() === $filter)
            ->map(fn (Invite $invite): array => [
                $invite->code,
                $invite->createdBy->nickname ?? $invite->createdBy->email,
                "{$invite->uses_count} de {$invite->max_uses}",
                $invite->expires_at?->format('d/m/Y') ?? 'nunca',
                $invite->status()->label(),
            ]);

        $this->table(['Código', 'Criador', 'Usos', 'Expira', 'Status'], $rows->all());

        return self::SUCCESS;
    }
}

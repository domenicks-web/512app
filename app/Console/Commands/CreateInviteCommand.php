<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\CreateInvite;
use App\Actions\Data\CreateInviteData;
use App\Models\User;
use Illuminate\Console\Command;

class CreateInviteCommand extends Command
{
    protected $signature = 'invites:create
        {--by= : Email do admin que assina o convite (padrão: o primeiro admin)}
        {--max-uses=1 : Quantas contas o convite pode criar}
        {--unlimited : Convite sem limite de usos (ignora --max-uses)}
        {--expires-in-days= : Dias até expirar (vazio = nunca expira)}';

    protected $description = 'Cria um código de convite pro cadastro, sem precisar do painel de admin';

    public function handle(CreateInvite $action): int
    {
        $creator = $this->resolveCreator();

        if ($creator === null) {
            $this->error('Nenhum admin encontrado. Passe --by=email de um usuário com is_admin.');

            return self::FAILURE;
        }

        $maxUses = $this->option('unlimited') ? null : $this->positiveIntOption('max-uses');
        $expiresInDays = $this->option('expires-in-days') !== null ? $this->positiveIntOption('expires-in-days') : null;

        if ($maxUses === 0 || $expiresInDays === 0) {
            $this->error('--max-uses e --expires-in-days precisam ser inteiros maiores que zero.');

            return self::FAILURE;
        }

        $invite = $action->handle($creator, new CreateInviteData(
            maxUses: $maxUses,
            expiresAt: $expiresInDays !== null ? now()->addDays($expiresInDays) : null,
        ));

        $this->info("Convite criado: {$invite->code}");
        $this->line('Usos: '.($invite->max_uses ?? 'infinito'));
        $this->line('Expira: '.($invite->expires_at?->format('d/m/Y H:i') ?? 'nunca'));

        return self::SUCCESS;
    }

    private function resolveCreator(): ?User
    {
        $email = $this->option('by');

        $query = User::where('is_admin', true);

        return $email !== null
            ? $query->where('email', $email)->first()
            : $query->orderBy('id')->first();
    }

    /**
     * Devolve 0 pra qualquer valor que não seja inteiro positivo, pra o
     * handle() tratar tudo como erro num lugar só.
     */
    private function positiveIntOption(string $name): int
    {
        $value = filter_var($this->option($name), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $value === false ? 0 : $value;
    }
}

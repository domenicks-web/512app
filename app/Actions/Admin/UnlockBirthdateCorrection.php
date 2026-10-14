<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;

/** Deixa o jogador corrigir a data de nascimento mais uma vez. */
class UnlockBirthdateCorrection
{
    public function handle(User $user): void
    {
        $user->forceFill(['birthdate_corrected_at' => null])->save();
    }
}

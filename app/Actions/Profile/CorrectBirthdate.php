<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * A data de nascimento pode ser corrigida uma vez. Depois trava até um
 * admin liberar (`Admin\UnlockBirthdateCorrection`).
 */
class CorrectBirthdate
{
    public function handle(User $user, CarbonInterface $birthdate, CarbonInterface $now): void
    {
        if ($user->birthdate_corrected_at !== null) {
            throw ValidationException::withMessages([
                'birthdate' => 'Você já corrigiu a data de nascimento. Fale com um admin pra liberar outra correção.',
            ]);
        }

        $user->update(['birthdate' => $birthdate]);
        $user->forceFill(['birthdate_corrected_at' => $now])->save();
    }
}

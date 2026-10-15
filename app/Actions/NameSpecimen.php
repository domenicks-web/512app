<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\SpecimenNamingNotAllowedException;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * O dono dá o apelido ao espécime, uma vez só: depois de gravado fica
 * travado até o espécime mudar de dono (a troca e a libertação limpam).
 * Lê de novo com lock, pra dois toques rápidos não gravarem dois apelidos.
 */
class NameSpecimen
{
    public function handle(Specimen $specimen, User $owner, string $nickname): Specimen
    {
        return DB::transaction(function () use ($specimen, $owner, $nickname): Specimen {
            $locked = Specimen::query()->whereKey($specimen->id)->lockForUpdate()->firstOrFail();

            if ($locked->user_id !== $owner->id) {
                throw SpecimenNamingNotAllowedException::notOwner();
            }

            if ($locked->nickname !== null) {
                throw SpecimenNamingNotAllowedException::alreadyNamed();
            }

            $locked->forceFill(['nickname' => $nickname])->save();

            return $locked;
        });
    }
}

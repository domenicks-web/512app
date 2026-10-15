<?php

declare(strict_types=1);

namespace App\Http\Controllers\Specimen;

use App\Actions\NameSpecimen;
use App\Exceptions\SpecimenNamingNotAllowedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\NameSpecimenRequest;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class NameSpecimenController extends Controller
{
    public function __invoke(NameSpecimenRequest $request, Specimen $specimen, NameSpecimen $action): RedirectResponse
    {
        /** @var User $owner */
        $owner = $request->user();

        try {
            $action->handle($specimen, $owner, $request->nickname());
        } catch (SpecimenNamingNotAllowedException $exception) {
            return back()->withErrors(['nickname' => $exception->getMessage()]);
        }

        return back();
    }
}

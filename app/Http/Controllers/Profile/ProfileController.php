<?php

declare(strict_types=1);

namespace App\Http\Controllers\Profile;

use App\Actions\Profile\CancelEmailChange;
use App\Actions\Profile\ChangeNickname;
use App\Actions\Profile\ChangePassword;
use App\Actions\Profile\ConfirmEmailChange;
use App\Actions\Profile\CorrectBirthdate;
use App\Actions\Profile\RequestEmailChange;
use App\Actions\Profile\UpdateAvatar;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeNicknameRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\CorrectBirthdateRequest;
use App\Http\Requests\RequestEmailChangeRequest;
use App\Http\Requests\UpdateAvatarRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Cada seção da tela de perfil salva por uma rota própria. Toda regra mora
 * nas Actions de `app/Actions/Profile/`; aqui só valida e delega.
 */
class ProfileController extends Controller
{
    public function updateAvatar(UpdateAvatarRequest $request, UpdateAvatar $action): RedirectResponse
    {
        $action->handle($this->user($request), $request->integer('avatar_species_id'), $request->boolean('avatar_is_shiny'));

        return back()->with('profile_status', 'Avatar salvo.');
    }

    public function updateNickname(ChangeNicknameRequest $request, ChangeNickname $action): RedirectResponse
    {
        $action->handle($this->user($request), $request->nickname(), now());

        return back()->with('profile_status', 'Nickname trocado.');
    }

    public function updateBirthdate(CorrectBirthdateRequest $request, CorrectBirthdate $action): RedirectResponse
    {
        $action->handle($this->user($request), $request->birthdate(), now());

        return back()->with('profile_status', 'Data de nascimento corrigida.');
    }

    public function requestEmail(RequestEmailChangeRequest $request, RequestEmailChange $action): RedirectResponse
    {
        $action->handle($this->user($request), $request->newEmail());

        return back()->with('profile_status', 'Mandamos um link pro email novo.');
    }

    public function cancelEmail(Request $request, CancelEmailChange $action): RedirectResponse
    {
        $action->handle($this->user($request));

        return back()->with('profile_status', 'Troca de email cancelada.');
    }

    public function confirmEmail(Request $request, ConfirmEmailChange $action): RedirectResponse
    {
        $status = $action->handle($this->user($request), $request->string('email')->toString());

        return redirect()->route('profile.edit')->with('profile_status', $status);
    }

    public function updatePassword(ChangePasswordRequest $request, ChangePassword $action): RedirectResponse
    {
        $action->handle($this->user($request), $request->newPassword(), $request->session()->getId());

        return back()->with('profile_status', 'Senha trocada. Os outros aparelhos foram desconectados.');
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}

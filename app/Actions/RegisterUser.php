<?php

declare(strict_types=1);

namespace App\Actions;

use App\Actions\Data\RegisterUserData;
use App\Exceptions\InviteNotRedeemableException;
use App\Models\Invite;
use App\Models\InviteRedemption;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterUser
{
    public function handle(RegisterUserData $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $invite = Invite::where('code', $data->inviteCode)->lockForUpdate()->first();

            if ($invite === null || $invite->isExpired() || ! $invite->hasUsesLeft()) {
                throw InviteNotRedeemableException::code($data->inviteCode);
            }

            $user = User::create([
                'email' => $data->email,
                'password' => $data->password,
                'invited_by' => $invite->id,
                'client_seed' => Str::random(32),
            ]);

            InviteRedemption::create([
                'invite_id' => $invite->id,
                'user_id' => $user->id,
            ]);

            $invite->increment('uses_count');

            return $user;
        });

        $user->sendEmailVerificationNotification();

        return $user;
    }
}

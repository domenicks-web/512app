<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class LoginWithGoogle
{
    public function handle(SocialiteUser $googleUser): User
    {
        $user = User::where('google_id', $googleUser->getId())->first();

        if ($user) {
            $user->update([
                'name' => $googleUser->getName() ?? $user->name,
                'avatar_url' => $googleUser->getAvatar(),
            ]);

            return $user;
        }

        return User::create([
            'name' => $googleUser->getName() ?? $googleUser->getNickname() ?? $googleUser->getEmail(),
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'avatar_url' => $googleUser->getAvatar(),
            'client_seed' => Str::random(32),
        ]);
    }
}

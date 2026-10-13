<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Actions\Data\CreateInviteData;
use App\Actions\GenerateInviteCode;
use App\Models\Invite;
use App\Models\User;

class CreateInvite
{
    public function __construct(private readonly GenerateInviteCode $generateInviteCode = new GenerateInviteCode) {}

    public function handle(User $creator, CreateInviteData $data): Invite
    {
        return Invite::create([
            'code' => $this->generateInviteCode->handle($creator),
            'created_by' => $creator->id,
            'max_uses' => $data->maxUses,
            'expires_at' => $data->expiresAt,
        ]);
    }
}

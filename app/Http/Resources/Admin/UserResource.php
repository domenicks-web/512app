<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'tag' => $this->tag,
            'email' => $this->email,
            'is_admin' => $this->is_admin,
            'has_completed_profile' => $this->hasCompletedProfile(),
            'is_birthdate_locked' => $this->birthdate_corrected_at !== null,
            'invite_code' => $this->invitedBy?->code,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

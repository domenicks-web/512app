<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class PlayerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // `SearchPlayers` carrega só as sanções ativas, a mais recente primeiro.
        $sanction = $this->relationLoaded('sanctions') ? $this->sanctions->first() : $this->activeSanction();

        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'tag' => $this->tag,
            'handle' => $this->nicknameWithTag() ?? $this->email,
            'email' => $this->email,
            'avatar_sprite_path' => $this->avatarSpritePath(),
            'is_admin' => $this->is_admin,
            'invite_quota' => $this->invite_quota,
            'is_email_verified' => $this->hasVerifiedEmail(),
            'is_birthdate_locked' => $this->birthdate_corrected_at !== null,
            'has_pack_available' => $this->next_pack_at === null || $this->next_pack_at->isPast(),
            'invite_code' => $this->invitedBy?->code,
            'invited_by' => $this->invitedBy?->createdBy?->nickname,
            'specimens_count' => (int) $this->specimens_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'sanction' => $sanction === null ? null : [
                'type' => $sanction->type->value,
                'endsAt' => $sanction->ends_at?->toIso8601String(),
                'reason' => $sanction->reason,
            ],
        ];
    }
}

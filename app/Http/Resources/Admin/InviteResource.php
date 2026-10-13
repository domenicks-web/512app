<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Invite;
use App\Models\InviteRedemption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invite
 */
class InviteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status()->value,
            'max_uses' => $this->max_uses,
            'uses_count' => $this->uses_count,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'created_by' => $this->createdBy->nickname ?? $this->createdBy->email,
            'redemptions' => $this->redemptions->map(fn (InviteRedemption $redemption): array => [
                'user' => $redemption->user->nickname !== null
                    ? "{$redemption->user->nickname}#{$redemption->user->tag}"
                    : $redemption->user->email,
                'redeemed_at' => $redemption->created_at->toIso8601String(),
            ])->all(),
        ];
    }
}

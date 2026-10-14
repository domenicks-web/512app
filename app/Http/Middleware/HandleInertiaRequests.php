<?php

namespace App\Http\Middleware;

use App\Models\Friendship;
use App\Models\Trade;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'avatarSpritePath' => fn (): ?string => $request->user()?->avatarSpritePath(),
            ],
            'packStatus' => fn (): ?array => $request->user() === null ? null : [
                'nextPackAt' => $request->user()->next_pack_at?->isFuture()
                    ? $request->user()->next_pack_at->toIso8601String()
                    : null,
                'cooldownHours' => (int) config('game.pack.cooldown_hours'),
            ],
            'pendingFriendRequests' => fn (): int => $request->user() === null
                ? 0
                : Friendship::query()->pending()->where('addressee_id', $request->user()->id)->count(),
            'pendingTrades' => fn (): int => $request->user() === null
                ? 0
                : Trade::query()->open()->where('to_user_id', $request->user()->id)->count(),
            'flash' => [
                'profile_status' => fn () => $request->session()->get('profile_status'),
            ],
        ];
    }
}

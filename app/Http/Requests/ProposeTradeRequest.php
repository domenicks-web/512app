<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Formato da proposta e da contraproposta (as regras ficam nas Actions). */
class ProposeTradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $max = (int) config('game.trades.max_items_per_side');

        return [
            'friend_id' => [$this->routeIs('trades.store') ? 'required' : 'prohibited', 'integer', 'exists:users,id'],
            'offered' => ['required', 'array', 'min:1', "max:{$max}"],
            'offered.*' => ['integer', 'distinct'],
            'requested' => ['required', 'array', 'min:1', "max:{$max}"],
            'requested.*' => ['integer', 'distinct'],
            'message' => ['nullable', 'string', 'max:'.(int) config('game.trades.message_max_length')],
        ];
    }

    /** @return array<int, int> */
    public function offeredIds(): array
    {
        return array_map('intval', (array) $this->input('offered'));
    }

    /** @return array<int, int> */
    public function requestedIds(): array
    {
        return array_map('intval', (array) $this->input('requested'));
    }

    public function message(): ?string
    {
        return $this->filled('message') ? $this->string('message')->toString() : null;
    }
}

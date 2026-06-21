<?php

namespace App\Actions;

use App\Models\Generation;

/**
 * Action: AiGate
 *
 * Pre-call safeguard (SCOPE_V1): the master kill-switch and the daily budget
 * cap (denial-of-wallet). Checked BEFORE any model call — if blocked, the chat
 * shows a notice and spends nothing.
 */
class AiGate
{
    /**
     * Human-readable reason the assistant is unavailable, or null when OK.
     */
    public function blockedReason(): ?string
    {
        if (! config('chat.ai_enabled')) {
            return 'Asystent jest chwilowo wyłączony. Spróbuj później.';
        }

        if ($this->spentToday() >= (float) config('chat.daily_budget_usd')) {
            return 'Dzienny limit zapytań został wyczerpany. Spróbuj ponownie jutro.';
        }

        return null;
    }

    /**
     * Total model cost (USD) charged today.
     */
    public function spentToday(): float
    {
        return (float) Generation::whereDate('created_at', today())->sum('cost');
    }
}

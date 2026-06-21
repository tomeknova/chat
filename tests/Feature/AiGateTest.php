<?php

namespace Tests\Feature;

use App\Actions\AiGate;
use App\Models\Generation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_kill_switch_blocks(): void
    {
        config(['chat.ai_enabled' => false, 'chat.daily_budget_usd' => 1.0]);

        $reason = app(AiGate::class)->blockedReason();

        $this->assertNotNull($reason);
        $this->assertStringContainsString('wyłączony', $reason);
    }

    public function test_under_budget_is_allowed(): void
    {
        config(['chat.ai_enabled' => true, 'chat.daily_budget_usd' => 1.0]);

        Generation::factory()->create(['cost' => 0.10]);

        $this->assertNull(app(AiGate::class)->blockedReason());
    }

    public function test_over_budget_blocks(): void
    {
        config(['chat.ai_enabled' => true, 'chat.daily_budget_usd' => 1.0]);

        Generation::factory()->count(2)->create(['cost' => 0.60]); // 1.20 >= 1.00

        $reason = app(AiGate::class)->blockedReason();

        $this->assertNotNull($reason);
        $this->assertStringContainsString('limit', $reason);
    }

    public function test_spent_today_sums_only_todays_cost(): void
    {
        Generation::factory()->create(['cost' => 0.25]);

        $this->assertEqualsWithDelta(0.25, app(AiGate::class)->spentToday(), 0.0000001);
    }
}

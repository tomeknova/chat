<?php

namespace Tests\Feature;

use App\AskDocs\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['askdocs.breaker.threshold' => 3, 'askdocs.breaker.window' => 60, 'askdocs.breaker.cooldown' => 30]);
    }

    public function test_opens_only_after_threshold_failures(): void
    {
        $breaker = new CircuitBreaker;

        $breaker->recordFailure('bielik');
        $breaker->recordFailure('bielik');
        $this->assertFalse($breaker->isOpen('bielik'));

        $breaker->recordFailure('bielik');
        $this->assertTrue($breaker->isOpen('bielik'));
    }

    public function test_success_resets_the_failure_count(): void
    {
        $breaker = new CircuitBreaker;

        $breaker->recordFailure('bielik');
        $breaker->recordFailure('bielik');
        $breaker->recordSuccess('bielik');

        // Counter reset → two more failures must not trip it.
        $breaker->recordFailure('bielik');
        $breaker->recordFailure('bielik');
        $this->assertFalse($breaker->isOpen('bielik'));
    }

    public function test_reopens_for_other_provider_only(): void
    {
        $breaker = new CircuitBreaker;

        $breaker->recordFailure('bielik');
        $breaker->recordFailure('bielik');
        $breaker->recordFailure('bielik');

        $this->assertTrue($breaker->isOpen('bielik'));
        $this->assertFalse($breaker->isOpen('openrouter'));
    }

    public function test_half_opens_after_cooldown(): void
    {
        $breaker = new CircuitBreaker;

        $breaker->recordFailure('bielik');
        $breaker->recordFailure('bielik');
        $breaker->recordFailure('bielik');
        $this->assertTrue($breaker->isOpen('bielik'));

        $this->travel(31)->seconds();
        $this->assertFalse($breaker->isOpen('bielik')); // cooldown expired
    }

    public function test_disabled_when_threshold_zero(): void
    {
        config(['askdocs.breaker.threshold' => 0]);
        $breaker = new CircuitBreaker;

        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFailure('bielik');
        }

        $this->assertFalse($breaker->isOpen('bielik'));
    }
}

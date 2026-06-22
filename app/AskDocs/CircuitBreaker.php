<?php

namespace App\AskDocs;

use Illuminate\Support\Facades\Cache;

/**
 * Per-provider circuit breaker. After `threshold` fallbackable failures within
 * `window` seconds the provider is "open" (skipped) for `cooldown` seconds, so
 * the failover chain fast-fails to the next provider instead of waiting on one
 * that is down. After the cooldown the provider is tried again (half-open via
 * TTL expiry): success resets it, another failure re-opens it. State lives in
 * the cache (works on any backend; exact atomicity is not required for counts).
 */
class CircuitBreaker
{
    private readonly int $threshold;

    private readonly int $window;

    private readonly int $cooldown;

    public function __construct()
    {
        $this->threshold = (int) config('askdocs.breaker.threshold', 3);
        $this->window = (int) config('askdocs.breaker.window', 60);
        $this->cooldown = (int) config('askdocs.breaker.cooldown', 30);
    }

    public function isOpen(string $provider): bool
    {
        if ($this->threshold <= 0) {
            return false; // breaker disabled
        }

        return Cache::get($this->openKey($provider)) !== null;
    }

    public function recordSuccess(string $provider): void
    {
        Cache::forget($this->failKey($provider));
        Cache::forget($this->openKey($provider));
    }

    public function recordFailure(string $provider): void
    {
        if ($this->threshold <= 0) {
            return;
        }

        $fails = ((int) Cache::get($this->failKey($provider), 0)) + 1;

        if ($fails >= $this->threshold) {
            Cache::put($this->openKey($provider), true, $this->cooldown);
            Cache::forget($this->failKey($provider));

            return;
        }

        Cache::put($this->failKey($provider), $fails, $this->window);
    }

    private function failKey(string $provider): string
    {
        return "askdocs:breaker:fails:{$provider}";
    }

    private function openKey(string $provider): string
    {
        return "askdocs:breaker:open:{$provider}";
    }
}

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AskDocs — providers & routing
    |--------------------------------------------------------------------------
    |
    | Named, OpenAI-compatible providers. `default` selects the active one;
    | AskDocsServiceProvider binds the ChatModel contract to the matching
    | adapter (decision M/N). Renamed from config/ai.php to avoid colliding
    | with the laravel/ai SDK, which publishes its own config/ai.php.
    |
    */

    'default' => env('ASKDOCS_PROVIDER', 'openrouter'),

    // Optional second provider tried when the primary fails / can't ground
    // (grounding-in-attempt + failover). e.g. ASKDOCS_PROVIDER=bielik + ASKDOCS_FALLBACK=openrouter.
    'fallback' => env('ASKDOCS_FALLBACK'),

    'providers' => [

        'openrouter' => [
            'driver' => 'openrouter',
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            'key' => env('OPENROUTER_API_KEY'),
            'model' => env('AI_MODEL', 'openai/gpt-5.4-nano'),
            // OpenRouter `provider.only` — providers that support structured outputs.
            'providers' => ['openai', 'azure'],
        ],

        'bielik' => [
            'driver' => 'ollama',
            'base_url' => env('BIELIK_BASE_URL', 'http://localhost:11434/v1'),
            'key' => env('BIELIK_KEY'), // local Ollama has no auth
            'model' => env('BIELIK_MODEL', 'bielik-11b-v3-q80:latest'),
            'timeout' => (int) env('BIELIK_TIMEOUT', 12), // small local model: fail fast to fallback
            // Prod: resolve by NAME (never a hardcoded IP). When `host` is set the
            // endpoint is resolved via DNS, pinned to the IP, and must pass the CIDR
            // allowlist (anti-SSRF) before any traffic. Unset (local) → static base_url.
            'host' => env('BIELIK_HOST'),
            'port' => (int) env('BIELIK_PORT', 11434),
            'allowed_cidr' => env('BIELIK_ALLOWED_CIDR'), // e.g. 192.168.10.0/24 (comma-separated)
            'resolve_ttl' => (int) env('BIELIK_RESOLVE_TTL', 30),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Retrieval (staged recall before the model selects)
    |--------------------------------------------------------------------------
    |
    | `full` sends the whole corpus (OK for big-context providers like OpenRouter).
    | `lexical` narrows to top_k candidates first — REQUIRED for a small local
    | model (Bielik): full corpus overruns its context. Behind CandidateRetriever
    | (swap without touching AskDocs/validator). bge-m3 semantic recall = next stage.
    |
    */

    'retrieval' => [
        'driver' => env('ASKDOCS_RETRIEVER', 'full'), // full | lexical
        'top_k' => (int) env('ASKDOCS_TOP_K', 8),
        'max_chars' => (int) env('ASKDOCS_MAX_CHARS', 12000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit breaker (per provider)
    |--------------------------------------------------------------------------
    |
    | After `threshold` fallbackable failures within `window` seconds, a provider
    | is skipped for `cooldown` seconds (fast fallback instead of hammering a
    | provider that is down). State lives in the cache. threshold=0 disables it.
    |
    */

    'breaker' => [
        'threshold' => (int) env('ASKDOCS_BREAKER_THRESHOLD', 3),
        'window' => (int) env('ASKDOCS_BREAKER_WINDOW', 60),
        'cooldown' => (int) env('ASKDOCS_BREAKER_COOLDOWN', 30),
    ],

    'max_tokens' => (int) env('AI_MAX_TOKENS', 1024),

    'timeout' => (int) env('AI_TIMEOUT', 30),

    // Total request budget across the failover chain (deadline-aware). 0 = disabled.
    'deadline' => (int) env('ASKDOCS_DEADLINE', 35),

];

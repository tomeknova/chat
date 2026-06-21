<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI — OpenRouter (OpenAI-compatible)
    |--------------------------------------------------------------------------
    |
    | The assistant only SELECTS the right approved answer-unit, so a cheap
    | model suffices. `providers` pins structured-output-capable providers
    | (OpenRouter `provider.only`) so `response_format` is guaranteed.
    |
    */

    'model' => env('AI_MODEL', 'openai/gpt-5.4-nano'),

    'fallback_model' => env('AI_FALLBACK_MODEL', 'mistralai/ministral-14b-2512'),

    'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),

    'key' => env('OPENROUTER_API_KEY'),

    'providers' => ['openai', 'azure'],

];

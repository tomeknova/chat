<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chat assistant — runtime settings
    |--------------------------------------------------------------------------
    |
    | owner_token_pepper: secret mixed into the anonymous owner token before
    | hashing (conversations.owner_token_hash). RODO erasure keys off the hash.
    | Defaults to APP_KEY; set a dedicated value in production.
    |
    */

    'owner_token_pepper' => env('OWNER_TOKEN_PEPPER', env('APP_KEY', '')),

    /*
    |--------------------------------------------------------------------------
    | AI safeguards (public traffic, real cost)
    |--------------------------------------------------------------------------
    |
    | ai_enabled: master kill-switch — when false the assistant stops calling
    | the model and replies with a notice. daily_budget_usd: denial-of-wallet
    | cap; once today's summed generation cost reaches it, calls are blocked
    | until tomorrow.
    |
    */

    'ai_enabled' => (bool) env('AI_ENABLED', true),

    'daily_budget_usd' => (float) env('AI_DAILY_BUDGET_USD', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Starter suggestions (guide the hesitant user)
    |--------------------------------------------------------------------------
    |
    | Clickable chips shown under the welcome message. Keep them ANSWERABLE
    | (mapped to real corpus topics) — a chip that leads to an abstention erodes
    | trust. Recovery suggestions on abstention are derived live from the corpus
    | intents of the nearest retrieved units (see App\Actions\AskDocs).
    |
    */

    'suggestions' => [
        'Jak utworzyć nowe wydarzenie?',
        'Jak zmienić logo i kolory strony?',
        'Jakie rozmiary zdjęć obowiązują w panelu?',
    ],

];

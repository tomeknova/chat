<?php

/*
|--------------------------------------------------------------------------
| Docs corpus — profiles (multi-instruction) · SCOPE_V1: file, not version tables
|--------------------------------------------------------------------------
|
| The assistant serves ONE instruction at a time but the build refreshes ALL
| profiles (cron) into separate artifacts spun together by a manifest. Each
| profile carries its own source repo, public base_url and UI data (label,
| starter suggestions, greeting) — one code path, data-driven (no per-project
| methods). See docs/MULTI_CORPUS.md.
|
| Per-request the active profile is selected in the UI (Faza 2) by overriding
| the derived `corpus.*` keys; here we resolve the DEFAULT profile so existing
| code reading `corpus.output_path` / `corpus.base_url` keeps working unchanged.
|
*/

$profiles = [

    'kings5-docs' => [
        'label' => 'KINGS',
        'enabled' => (bool) env('CORPUS_KINGS5_ENABLED', true),
        // Back-compat: honour the old single-source env names as kings5 defaults.
        'source_path' => env('CORPUS_KINGS5_SOURCE', env('CORPUS_SOURCE_PATH', '/opt/lampp/htdocs/kings5-docs')),
        'base_url' => env('DOCS_KINGS5_BASE_URL', env('DOCS_BASE_URL', '')),
        'greeting' => 'Witaj! Zadaj pytanie o panel KINGS — odpowiem wyłącznie na podstawie dokumentacji i wskażę źródło.',
        'suggestions' => [
            'Jak utworzyć nowe wydarzenie?',
            'Jak zmienić logo i kolory strony?',
            'Jakie rozmiary zdjęć obowiązują w panelu?',
        ],
    ],

    'clams-docs' => [
        'label' => 'CLAMS',
        'enabled' => (bool) env('CORPUS_CLAMS_ENABLED', true),
        'source_path' => env('CORPUS_CLAMS_SOURCE', '/opt/lampp/htdocs/clams-docs'),
        'base_url' => env('DOCS_CLAMS_BASE_URL', ''),
        'greeting' => 'Witaj! Zadaj pytanie o panel CLAMS — odpowiem wyłącznie na podstawie dokumentacji i wskażę źródło.',
        'suggestions' => [
            'Jak nadać dostęp przewodniczącemu oddziału?',
            'Przewodniczący widzi błąd 403 — co sprawdzić?',
        ],
    ],

];

// Default profile (start of a new visitor / fallback). The real active profile
// is chosen in the UI per conversation. Validate LOUDLY — a typo must fail with a
// clear message, never silently fall back to another instruction (audit #3).
$default = (string) env('CORPUS_PROFILE', 'kings5-docs');

if (! array_key_exists($default, $profiles)) {
    throw new RuntimeException(
        "Nieprawidłowy CORPUS_PROFILE='{$default}'. Dozwolone profile: ".implode(', ', array_keys($profiles)).'.'
    );
}

$active = $profiles[$default];
$outputDir = storage_path('app/corpus');

return [

    /** @var array<string, array{label: string, enabled: bool, source_path: string, base_url: string, greeting: string, suggestions: list<string>}> */
    'profiles' => $profiles,

    // Default profile name; `active_profile` is the one resolved for THIS request
    // (overridden per-request by applyProfile() in Faza 2 — here it equals default).
    'default' => $default,
    'active_profile' => $default,

    // Directory holding all corpus-*.json artifacts + corpus-master.json manifest.
    'output_dir' => $outputDir,

    /*
    | Active-profile derived keys — existing code reading these keeps working.
    | chat:build-corpus writes per-profile; the retriever reads `output_path`.
    */
    'source_path' => $active['source_path'],
    'output_path' => $outputDir.'/corpus-'.$default.'.json',
    'base_url' => $active['base_url'],
    'greeting' => $active['greeting'],
    'suggestions' => $active['suggestions'],

    // Only pages whose frontmatter sets this key truthy enter the corpus
    // (human-review anti-injection boundary).
    'approval_key' => env('CORPUS_APPROVAL_KEY', 'assistant'),

    /** Files excluded from the corpus (mirror VitePress `srcExclude`). @var list<string> */
    'exclude' => ['README.md', 'DEPLOY-SERVER.md', 'CLAUDE.md'],

];

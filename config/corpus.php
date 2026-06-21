<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Docs corpus (file, not version tables — SCOPE_V1)
    |--------------------------------------------------------------------------
    |
    | chat:build-corpus reads VitePress markdown from `source_path`, cuts each
    | APPROVED page into answer-units (by H2/H3) and writes them to a single
    | JSON file at `output_path`. Only pages whose frontmatter sets the
    | `approval_key` truthy enter the corpus — the human-review anti-injection
    | boundary. `base_url` is prepended to canonical_url at render time.
    |
    */

    'source_path' => env('CORPUS_SOURCE_PATH', '/opt/lampp/htdocs/kings5-docs'),

    'output_path' => env('CORPUS_OUTPUT_PATH', storage_path('app/corpus/corpus.json')),

    'base_url' => env('DOCS_BASE_URL', ''),

    'approval_key' => env('CORPUS_APPROVAL_KEY', 'assistant'),

    /**
     * Files excluded from the corpus (mirror VitePress `srcExclude`).
     *
     * @var list<string>
     */
    'exclude' => ['README.md', 'DEPLOY-SERVER.md', 'CLAUDE.md'],

];

# CLAUDE.md — KINGS Docs — Asystent AI (chat)

> Szablon konwencji zaadaptowany ze sprawdzonego układu projektu KINGS5
> (sam format + uniwersalne zasady; treść specyficzna dla TEGO projektu, bez domeny KINGS5).
> Źródło konwencji: WYŁĄCZNIE ten plik + `docs/` + kod (nie katalogi spoza repo).
>
> **WIĄŻĄCE (czytaj NAJPIERW):** zakres v1, kontrakt AI, model danych i architektura =
> `docs/SCOPE_V1.md` + `docs/KICKOFF_V1.md` (najnowsze). Rdzeń = **grounded WYBÓR answer-unit**
> (`{response_type, answer_unit_ids[]}`, walidacja `∈ generation_context` + `content_hash`).
> Sekcje STACK / MODEL DANYCH / ARCHITEKTURA / ROZMIESZCZENIE zaktualizowano po integracji Bielika (stan = kod).
> **W razie sprzeczności obowiązują kod + `docs/`** (precedencja źródeł: `docs/AGENT_GUIDE.md`).

## RESPONSE LANGUAGE
Odpowiadaj po **polsku**. Komentarze w kodzie po **angielsku**. Kodowanie UTF-8.

## CEL PROJEKTU
Jednostronicowy **pomocnik AI** do dokumentacji panelu KINGS. Użytkownik zadaje
pytanie → Claude odpowiada **wyłącznie na podstawie dokumentacji** + zwraca **link**
do właściwej strony instrukcji. Ocena 👍/👎 zasila pętlę curation (admin pisze
właściwą odpowiedź → wraca do asystenta). Bez fine-tuningu — poprawa w kontekście.

## STACK
- Laravel 12 / PHP 8.2+ (local LAMPP: **8.2** · serwer prod: **8.5**)
- Livewire (preferencja: 4, jak w KINGS5) — publiczny, jednostronicowy czat
- Filament 5 — panel review (pytania + zatwierdzone odpowiedzi)
- **MySQL/MariaDB** — połączenie Laravel **`mysql`** (przenośne: local=MariaDB, prod=**MySQL 8.4 LTS**); baza `chat`. **5 tabel aplikacyjnych v1** (schemat = migracje `database/migrations/`; konfiguracja DB: `docs/DB_SETUP.md`)
- Tailwind; layout z BootstrapMade „Landia" (rama) + dymki czatu z szablonu admina
- AI: **hybryda** — Bielik (lokalny Ollama) primary + **OpenRouter** fallback (OpenAI-compatible, `config/askdocs.php`),
  model OpenRoutera **`openai/gpt-5.4-nano`**; strict JSON structured output + `provider.only` (tylko OpenRouter).
  Dla Bielika retriever **lexical**. Routing: `ASKDOCS_PROVIDER` / `ASKDOCS_FALLBACK` / `ASKDOCS_RETRIEVER`
- Język: kod EN, UI PL

## ŚRODOWISKO
- **Local:** https://chat.test (vhost Apache `127.0.1.21`, cert self-signed, DocumentRoot=`public/`).
  LAMPP: Apache = **`daemon`** → `storage/` + `bootstrap/cache/` muszą być zapisywalne (dev: 775/777). DB = MariaDB.
- **Prod (serwer):** PHP **8.5**, MySQL **8.4 LTS**. Połączenie `mysql` działa na obu środowiskach.
  Wymagane rozszerzenia PHP: `pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, bcmath, fileinfo, curl, json`.
  Na prod **NIE root** — dedykowany user DB; composer `"php": "^8.2"` (instaluje się i na 8.2, i na 8.5).

## KRYTYCZNE PUŁAPKI
1. **UTF-8** — polskie znaki psują się przy kopiuj-wklej+zapis. Weryfikuj: `grep -cP '[ÃÄÅ]' plik` → 0.
2. **Uprawnienia (LAMPP)** — Apache = `daemon`; `storage/` + `bootstrap/cache/` muszą być zapisywalne.
   Świeży 500 zaraz po wgraniu = zwykle brak praw zapisu do tych katalogów.
3. **Klucz API** — NIGDY w kodzie/gicie; tylko `.env` (`OPENROUTER_API_KEY`). Front nie
   widzi klucza — wszystkie wywołania przez serwerową Action.
4. **Kontekst i koszt** — korpus podawany w `system`; cachowanie zależy od dostawcy
   (OpenRouter/Ollama), NIE Anthropic `cache_control`. Mały model (Bielik) → retriever
   **lexical** (`ASKDOCS_RETRIEVER=lexical`); pełny korpus przepełnia jego kontekst.
5. **Model id** — `openai/gpt-5.4-nano` (slug OpenRouter, z `config/askdocs.php`). Structured output
   OpenAI-compatible: `response_format.json_schema` = `{name, strict:true, schema}` (płaska,
   `additionalProperties:false`) + `provider.only` (dostawcy z structured outputs).

## TWARDE ZASADY
1. Cienki controller/Livewire — logika w **Action**.
2. Sekrety tylko w `.env`, nigdy w kodzie.
3. Publiczny endpoint czatu → **throttle** (RateLimiter) — chroni przed kosztami/abuse.
4. Nowe pliki PL → czysty UTF-8.
5. Bez fine-tuningu — jakość przez edycję docs VitePress + `chat:build-corpus` (curation przez reindeks).
6. Bez nowych katalogów top-level w `app/`; bez zbędnych abstrakcji (YAGNI).

## ROZMIESZCZENIE PLIKÓW
- `app/Livewire/` — komponent czatu (single-page) + ewentualnie historia.
- `app/Actions/` — `AskDocs` (orkiestruje rezerwację + wybór jednostki), `Corpus/` (retrievery: `FullCorpus`, `Lexical`), `RedactPii`, `AiGate`.
- `app/AskDocs/` — moduł providerów: kontrakty (`ChatModel`, `AnswerUnitSelector`, `EndpointResolver`),
  adaptery (`OllamaChatModel`, `OpenRouterChatModel`), `Selection/FailoverAnswerUnitSelector`, `GroundingValidator`,
  `CircuitBreaker`, `Adapters/Discovery/DnsEndpointResolver`, `Security/EndpointAllowlist`.
- `app/Console/Commands/` — `chat:build-corpus`, `chat:assistant-smoke`, `chat:eval`.
- `app/Filament/Resources/` — `Messages`, `Generations` (review/telemetria, read-only).
- `config/askdocs.php` (providery/routing/retriever/breaker/lease/deadline) · `config/corpus.php` (base_url, ścieżki) · `config/chat.php` (pepper, kill-switch, budżet).

## MODEL DANYCH (5 tabel v1 — pełny schemat = migracje `database/migrations/`)
- `conversations` — `public_id` (ULID), `owner_token_hash`, `title`, `created_at`
- `messages` — `conversation_id`, `role` (user/assistant), `content`, `normalized_question_hash`, `product_status`, `rating` (up/down/null), `created_at`
- `generations` — ślad + rezerwacja (decyzja R): `operation_id` UNIQUE, `model`, `response_type`, tokeny, `cost`, `infra_status`,
  `status`, `processing_owner`, `lease_expires_at`, `request_fingerprint`, `execution_attempt`, `metadata` (JSON, `attempts[]`)
- `generation_context` — co model widział (`answer_unit_id`, `content_hash`) = podstawa walidacji
- `message_units` — werdykt groundingu per jednostka (`validation_status`, `display_ordinal`)

## ARCHITEKTURA ASYSTENTA
- **AskDocs (Action):** `system` = instrukcja + jednostki korpusu (UNTRUSTED); model **wybiera**
  jednostki → strict JSON `{response_type, answer_unit_ids[]}`. Backend renderuje TYLKO jednostkę
  z `generation_context` (walidacja `∈ context` + `content_hash`), link z manifestu — nie od modelu.
- **Korpus:** `chat:build-corpus` czyta markdowny z repo **kings5-docs** → plik w `storage`;
  odpalany przy deployu (i/lub cron) → asystent zawsze zna aktualne docs.
- **Pętla feedbacku:** 👎 → review w Filament → admin edytuje docs VitePress →
  `chat:build-corpus` → asystent zna poprawną treść. Kuracja = edycja docs + reindeks (bez tabeli `approved_answers`/`answer_drafts`).

## DEPLOY (git → GitHub prywatne → serwer pull)
- Repo: **`chat`** (prywatne, `github.com:tomeknova/chat`). Serwer pobiera przez **deploy key** (read-only). Przewodnik wdrożenia: `docs/DEPLOY.md`.
- `deploy.sh`: `git reset --hard origin/main` → `composer install --no-dev --optimize-autoloader`
  → `php artisan config:cache route:cache view:cache` → `migrate --force` → `chat:build-corpus`.
- `.env` tworzony **raz na serwerze** (klucz API, NIE w gicie). vhost docroot = `public/`.
  `storage/` + `database/` zapisywalne dla usera WWW.
- Subdomena docelowa: `chat.kings5-docs.mixpost.pl` (DNS+vhost+cert ręcznie; za Cloudflare
  Universal SSL na brzegu).

## STYL PRACY Z AGENTEM (uniwersalne preferencje usera)
- **Bez zmian w kodzie do jawnego „GO"** — najpierw plan/diff do akceptacji, potem edycja.
- **Push tylko na wyraźny sygnał** usera.
- Źródło konwencji: wyłącznie repo (ten plik + `docs/` + kod) — nie katalogi spoza repo.
- W local po naprawie obetnij `storage/logs/laravel.log`.
- Gdy konwencja niejasna → zapytaj krótko, nie zgaduj.

## CHECKLIST PRZED ZMIANĄ
- [ ] Logika w Action (nie w kontrolerze/Livewire)?
- [ ] Klucz/sekrety tylko w `.env`?
- [ ] Endpoint czatu z throttle?
- [ ] Structured output `{response_type, answer_unit_ids[]}` + walidacja `∈ generation_context` + `content_hash`?
- [ ] Retriever dobrany do providera (Bielik → `lexical`)?
- [ ] UTF-8 czysty w nowych plikach PL?
- [ ] Filament resource dla nowych danych?

## KOMENDY
```bash
php artisan serve
php artisan chat:build-corpus
php artisan migrate
npm run dev        # lub: npm run build
```

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.2
- filament/filament (FILAMENT) - v5
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>

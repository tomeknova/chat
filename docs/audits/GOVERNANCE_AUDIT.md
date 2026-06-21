# KINGS Docs — Asystent AI — AUDYT GOVERNANCE (instrukcje, konwencje, polityki)

> **Typ:** audyt dokumentacji/governance — plików konfigurujących projekt dla pracy z agentem AI. NIE audyt kodu.
> **Stack:** Laravel 12.62, Filament **5**.6.7, Livewire **4**.3.1, PHP 8.2 (local) / 8.5 (prod), MySQL. Front: Bootstrap 5 + SCSS (Vite); Tailwind tylko pod Filament. **Single-site, single-locale (PL).**
> **Stan projektu (KALIBRACJA — czytaj):** WCZESNY. Realnie istnieje: 1 komponent Livewire (czat, sam UI), front z szablonu Landia, panel Filament (pusty — bez Resource'ow). NIE istnieje jeszcze: tabele aplikacyjne (w bazie tylko users/cache/jobs), integracja AI (AskDocs/OpenRouter = STUB), testy, korpus docs. Dokumentacja/konwencje powstaly WCZESNIEJ niz kod, ktory maja regulowac.
> **Runda:** pierwszy audyt.

## Po co ten audyt (dwie rzeczy)
1. **Spojnosc / kompletnosc** plikow governance: sprzecznosci, luki, redundancja, zle odchudzenie z projektow-rodzenstwa multi-event (KINGS5 / CLAMS), z ktorych konwencje zaadaptowano.
2. **Swieze spojrzenie na otwarte problemy** (sekcja nizej) — oczekujemy **PROPOZYCJI ROZWIAZAN** (2-3 warianty z trade-offami), nie tylko diagnozy.

## Otwarte problemy — prosimy o propozycje rozwiazan
(To druga polowa wartosci audytu. Dla kazdego: konkretne podejscie, najlepiej warianty z trade-offami.)

- **A. Proporcja docs do kodu.** ~900 linii konwencji + CLAUDE.md + Boost dla aplikacji z 1 komponentem i bez tabel. Przerost? Co przyciac, co zostawic, by nie utrzymywac martwych regul?
- **B. Redundancja zrodel regul.** Nakladaja sie: nasze `CLAUDE.md`, blok Laravel Boost w `CLAUDE.md`, `BACKEND_CONVENTIONS.md`, oraz zywe `search-docs` Boosta (docs frameworka na zadanie). Jak rozdzielic odpowiedzialnosci (single source of truth per kategoria), zeby sie nie rozjezdzaly?
- **C. Integracja AI (jeszcze nie napisana).** `AskDocs` ma: czytac korpus docs, zwracac structured `{answer, link, covered}`, NIE zmyslac (`covered:false` gdy brak w docs), prompt caching, przez OpenRouter (OpenAI-compatible, model namespaced `anthropic/...`). Jak zrobic to solidnie: grounding/cytowanie, kontrola halucynacji, kontrola kosztu, timeout/retry, sync vs streaming, structured output przez OpenRouter (czy `response_format`/json_schema dziala dla wybranego modelu)?
- **D. Model danych (szkic, przed migracja).** `conversations(owner_token, title)`, `messages(conversation_id, role, content, ai_link, ai_covered, rating)`, `approved_answers(question, answer, link, is_active)`. Wytrzyma v2 (historia anonimowa per-przegladarka + petla curation)? Czego brakuje (indeksy, retencja, audyt, dedup pytan)?
- **E. Prywatnosc/bezpieczenstwo publicznego czatu.** Zapis pytan userow (token anon w cookie), throttle, prompt injection, RODO/retencja. Minimalny rozsadny zakres dla malego projektu?
- **F. Strategia korpusu docs — nierozstrzygnieta.** Skad asystent bierze tresc (porownujemy dwa istniejace projekty zrodlowe). Jak budowac i wersjonowac korpus, by byl aktualny i tani w kontekscie (prompt caching)?

## Zakres (deklaracja — co audytor weryfikuje, a co zgaduje)
- **ZALACZONE (pelne):** `CLAUDE.md` (z blokiem Boost), `docs/FRONTEND_CONVENTIONS.md`, `docs/BACKEND_CONVENTIONS.md`, `DEPLOY.md`, `docs/AUDIT_PACKAGE_GUIDELINES.md`, `composer.json`, `package.json`, `boost.json`, `.mcp.json`.
- **POMINIETE (zgadujesz, nie weryfikujesz):** realny kod `app/` i `resources/` (osobny audyt kodu), migracje (jeszcze nie ma), `.claude/skills/*` (dostarczone przez Boost, nie nasze), historia git, pamiec agenta.

---

## Zalacznik: CLAUDE.md

~~~~markdown
# CLAUDE.md — KINGS Docs — Asystent AI (chat)

> Szablon konwencji zaadaptowany ze sprawdzonego układu projektu KINGS5
> (sam format + uniwersalne zasady; treść specyficzna dla TEGO projektu, bez domeny KINGS5).
> Źródło konwencji: WYŁĄCZNIE ten plik + `docs/` + kod (nie katalogi spoza repo).

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
- **MySQL/MariaDB** — połączenie Laravel **`mysql`** (przenośne: local=MariaDB, prod=**MySQL 8.4 LTS**); baza `chat`. Tabele: pytania / feedback / rozmowy / approved_answers
- Tailwind; layout z BootstrapMade „Landia" (rama) + dymki czatu z szablonu admina
- AI: **Anthropic Claude API** (oficjalne PHP SDK), model **`claude-haiku-4-5`**,
  prompt caching + structured outputs
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
3. **Klucz API** — NIGDY w kodzie/gicie; tylko `.env` (`ANTHROPIC_API_KEY`). Front nie
   widzi klucza — wszystkie wywołania przez serwerową Action.
4. **Prompt caching** — korpus docs w `system` z `cache_control`; po breakpoincie
   zmienia się tylko pytanie. Sprawdzaj `cache_read_input_tokens > 0`.
5. **Model id** — dokładnie `claude-haiku-4-5` (bez sufiksu daty). Structured output:
   `output_config.format` z `json_schema`. PHP SDK używa camelCase (`maxTokens`).

## TWARDE ZASADY
1. Cienki controller/Livewire — logika w **Action**.
2. Sekrety tylko w `.env`, nigdy w kodzie.
3. Publiczny endpoint czatu → **throttle** (RateLimiter) — chroni przed kosztami/abuse.
4. Nowe pliki PL → czysty UTF-8.
5. Bez fine-tuningu — jakość przez `approved_answers` (curation w kontekście).
6. Bez nowych katalogów top-level w `app/`; bez zbędnych abstrakcji (YAGNI).

## ROZMIESZCZENIE PLIKÓW
- `app/Livewire/` — komponent czatu (single-page) + ewentualnie historia.
- `app/Actions/` — `AskDocs` (woła Claude), itp.
- `app/Console/Commands/` — `chat:build-corpus` (buduje korpus z markdownów kings5-docs).
- `app/Filament/Resources/` — `Questions`, `ApprovedAnswers` (review/curation).
- `config/docs.php` — bazowy URL docs, ścieżka źródła docs, model.

## MODEL DANYCH (szkic)
- `conversations` — `owner_token` (anon cookie), `title`, `created_at`
- `messages` — `conversation_id`, `role` (user/assistant), `content`, `ai_link`, `ai_covered`, `rating` (up/down/null), `created_at`
- `approved_answers` — `question`, `answer`, `link`, `is_active`

## ARCHITEKTURA ASYSTENTA
- **AskDocs (Action):** `system` = instrukcja + KORPUS DOCS (cache) + lista stron z URL;
  structured output `{answer, link, covered}`. `covered:false` = „brak w docs" (nie zmyślać).
- **Korpus:** `chat:build-corpus` czyta markdowny z repo **kings5-docs** → plik w `storage`;
  odpalany przy deployu (i/lub cron) → asystent zawsze zna aktualne docs.
- **Pętla feedbacku:** 👎 → review w Filament → `approved_answer` → serwowane przy
  trafieniu / wstrzykiwane jako wzorce do kontekstu modelu.

## DEPLOY (git → GitHub prywatne → serwer pull)
- Repo: **`kings5-docs-chat`** (prywatne). Serwer pobiera przez **deploy key** (read-only).
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
- [ ] Structured output `{answer, link, covered}` + zakaz zmyślania?
- [ ] Prompt caching na korpusie (cache_read > 0)?
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

~~~~

---

## Zalacznik: docs/FRONTEND_CONVENTIONS.md

~~~~markdown
# Konwencje frontendu — Blade, SCSS, JS, Livewire

> Zaadaptowane z KINGS5 (`docs/TEMPLATE_BUILD_POLICY.md`) i CLAMS (`docs/FRONTEND_CONVENTIONS.md`),
> **przepisane pod TEN projekt**: single-event, single-locale (PL), Bootstrap 5 + SCSS przez Vite.
> Maszyneria multi-event (templates/{key}, theme::, SectionDataResolver, content_blocks,
> {locale} w route) — **NIE dotyczy nas, świadomie pominięta**.
>
> Czytaj przed tworzeniem/zmianą KAŻDEGO pliku frontu. Sekcja 5 (SCSS) i 12 (zakazy) są
> krytyczne — tam mieszkają błędy, które już popełniałem.

---

## 0. Stack i zasada nadrzędna

- **Front publiczny:** Bootstrap 5.3 + SCSS, kompilowane przez **Vite** (`sass`). Wzorzec: KINGS5.
- **Tailwind** zostaje **wyłącznie pod Filament** (panel `/admin`) — nie używamy go na froncie publicznym.
- **Szablony (licencja):** Landia (rama) + EliteAdmin (dymki czatu). Ich źródła SCSS/markup są **źródłem prawdy** — adaptujemy, nie przepisujemy od zera.
- **Livewire 4** (z Filamenta) — interaktywność (czat).
- **Komentarze w kodzie: EN. UI: PL.** Pliki **czysty UTF-8** (polskie znaki literalne).

**Zasada nadrzędna:** zgodność ze standardem Laravela/frameworka i z konwencją szablonu —
**zero cichych obejść**. Każde odstępstwo sygnalizuj i uzasadnij.

---

## 1. Struktura plików frontu (single-event — bez `templates/{key}/`)

```
resources/views/
├── layouts/
│   ├── master.blade.php          # szkielet HTML (head, @vite, @livewireStyles/Scripts)
│   └── layout.blade.php          # @extends master; wstawia header/footer, content = @yield('contentpages')
├── partials/
│   ├── header.blade.php          # nawigacja (statyczna — single-event)
│   └── footer.blade.php
├── pages/
│   └── home.blade.php            # strona = @extends('layouts.layout') + @section('contentpages')
├── sections/
│   └── chat/
│       └── index.blade.php       # sekcja = entry 'index.blade.php' (osadza komponent Livewire)
└── livewire/
    ├── chat.blade.php            # widok komponentu Livewire
    └── partials/
        └── _message.blade.php    # partial reużywany (prefix '_')

resources/scss/                    # kompletne źródła Landii + nasz _chat
├── app.scss                       # entry Vite: @import bootstrap + bootstrap-icons + main
├── main.scss                      # @import _variables, layouts/*, _sections
├── _variables.scss                # zmienne CSS (paleta Landii + helpery z EliteAdmin)
├── _sections.scss                 # @import wszystkich sekcji (w tym sections/_chat)
├── layouts/                       # _general, _header, _footer, _navmenu, _scrolltop...
└── sections/                      # _hero, ..., _chat

resources/js/
├── app.js                         # import bootstrap.js + libki npm (aos/glightbox/swiper/purecounter) + zachowania szablonu
└── bootstrap.js                   # axios + import * as bootstrap -> window.bootstrap

app/Livewire/
└── Chat.php                       # komponent (PascalCase) -> render view('livewire.chat')
```

---

## 2. Nazewnictwo

| Element | Konwencja | Przykład |
|---|---|---|
| Katalogi sekcji | kebab-case | `sections/chat/`, `sections/hero/` |
| Entry sekcji | **zawsze** `index.blade.php` | `sections/chat/index.blade.php` |
| Partiale blade | prefiks `_` | `_message.blade.php` |
| Strony | kebab-case | `home.blade.php` |
| Pliki SCSS (partiale) | prefiks `_` | `_chat.scss`, `_header.scss` |
| Komponent Livewire | PascalCase klasa → kebab blade | `Chat.php` → `livewire/chat.blade.php` |
| Klasy CSS | **konwencja szablonu (BootstrapMade)** — `.block-element` (np. `.message-bubble`, `.chat-input-wrapper`) | NIE wymyślaj własnego schematu |

> **Klasy CSS:** używamy nazw z szablonów (Landia/EliteAdmin). Dla NOWYCH klas trzymaj się
> stylu BootstrapMade (`.komponent-element`, stany jako osobne klasy). Nie narzucaj własnego BEM,
> jeśli koliduje z szablonem.

---

## 3. Komentarz ścieżki — OBOWIĄZKOWY, 1. linia

Każdy plik Blade zaczyna się od komentarza ze ścieżką:

```blade
{{-- resources/views/sections/chat/index.blade.php --}}
```

Nienegocjowalne. Zapobiega pomyłkom plików przy pracy z agentem AI.

---

## 4. SCSS — ŹRÓDŁA i STRUKTURA

- **Źródła szablonu = źródło prawdy.** Kompletny `resources/scss/` Landii kopiujemy z licencji
  i adaptujemy. **NIE przepisujemy partiali Landii od zera** (to był błąd — robienie własnych
  `_header.scss` itd. zamiast użycia gotowych).
- **Entry `app.scss`** (wzorzec KINGS5): `@import "bootstrap/scss/bootstrap"; @import "bootstrap-icons/...";
  @import 'main.scss';` — Bootstrap jest osobnym vendorem (nie w `main.scss`).
- **Nowa sekcja → import w `_sections.scss`** (z resztą sekcji). **NIE doklejaj importów w `main.scss`**
  „żeby nie ruszać plików Landii" — to obejście. Sekcja jest częścią strony → idzie tam, gdzie sekcje.

## 5. SCSS — KOLORY (krytyczne — najczęstszy mój błąd)

- **Kolory WYŁĄCZNIE przez zmienne CSS z `_variables.scss`** (system kolorów BootstrapMade):
  `var(--accent-color)`, `var(--surface-color)`, `var(--background-color)`, `var(--default-color)`,
  `var(--heading-color)`, `var(--contrast-color)`, oraz helpery `var(--muted-color)`, `var(--border-color)`.
- **NIGDY nie hardkoduj hexów** (`#333`, `#fff`...) w partialach. Zmiana palety = zmiana w jednym
  miejscu (`_variables.scss`). Hardkod = łamanie systemu i niespójność.
- **Przyciemnienia/warianty:** `color-mix(in srgb, var(--accent-color), black 15%)` —
  jak w szablonie, nie własne hexy.
- **Helpery z EliteAdmin** dodajemy do **naszego** `_variables.scss`. **NIE importujemy
  `_variables.scss` admina** (ma swój fiolet `--accent-color:#6c5ce7`) — rdzeń palety zostaje Landii (navy).

```scss
// DOBRZE
.message-bubble { background: var(--background-color); color: var(--default-color); }
.message-group.sent .message-bubble { background: var(--accent-color); color: var(--contrast-color); }

// ŹLE — hardkod
.message-bubble { background: #f4f4f4; color: #505050; }
```

### Breakpoints + media queries zagnieżdżone w selektorze

```scss
.hero-text-center h1 {
  font-size: 3.25rem;

  @media (max-width: 768px) { font-size: 2.25rem; }   // 992 / 768 / 576
}
```

---

## 6. JS / `resources/js` — libki przez npm, nie vendor-pliki

- **Biblioteki szablonu instalujemy przez npm** (jak KINGS5), **nie kopiujemy `assets/vendor/*.js`**.
  Mamy: `aos`, `glightbox`, `swiper`, `@srexi/purecounterjs`, `bootstrap`, `bootstrap-icons`.
- **Import w `app.js`** (+ import CSS libek, gdzie trzeba): `import AOS from 'aos'; import 'aos/dist/aos.css';` itd.
- `bootstrap.js`: `axios` + `import * as bootstrap` → `window.bootstrap` (Modal/Dropdown/Collapse).
- Zachowania szablonu (mobile-nav, scrolltop, scrollspy, AOS.init...) = adaptacja `main.js` Landii,
  z null-guardami (strona bez danego elementu nie ma rzucać błędem).
- **Vite entry** rejestrujemy w `vite.config.js` (`input[]`): `resources/scss/app.scss`, `resources/js/app.js`.

---

## 7. Blade — wzorzec sekcji i dekompozycja

**Entry sekcji** (`sections/{name}/index.blade.php`) = wrapper `<section id class="... section">` + treść/komponent.
**Partial** (`_nazwa.blade.php`) = reużywany fragment, dane przekazywane jawnie przez `@include`.

**Dekompozycja — rozbij sekcję na partiale, gdy** (≥1 prawda):
1. więcej niż jeden blok koncepcyjny (header + body + CTA),
2. powtarzalny markup (karta/wiersz/dymek),
3. zagnieżdżone warunki,
4. > ~80 linii markupu,
5. fragment może być nadpisany/zmieniony niezależnie.

**Domyślnie: w razie wątpliwości — rozbij.** Monolityczny plik sekcji to błąd architektury frontu.

**Import klas w Blade:** klasy używane z `::` importuj przez `@use(...)` na górze pliku
(np. `@use('Illuminate\Support\Str')`). **Bez inline FQCN** (`\App\...`) w środku.

---

## 8. Livewire — konwencja (wg KINGS5, wariant single-event)

- **Klasa** `app/Livewire/Nazwa.php` (PascalCase) → **blade** `resources/views/livewire/nazwa.blade.php` (kebab).
  `render()` zwraca `view('livewire.nazwa')`. Auto-discovery Livewire 4 (bez ręcznej rejestracji).
- **Nagłówek klasy:** docblock ze ścieżką + opisem + `@see` blade. Sekcje banerowe
  (`// === PROPERTIES ===`, `LIFECYCLE`, `ACTIONS`, `RENDER`). Typowane `public` properties.
- **Cienki komponent — logika w Action.** Komponent orkiestruje stan + widok; właściwa logika
  (np. wywołanie AI) idzie do `app/Actions/` (zgodnie z CLAUDE.md).
- **Publiczny endpoint → throttle** (`RateLimiter`) + walidacja (`#[Validate]`).
- **Reużywany fragment** (dymek) → `livewire/partials/_message.blade.php`, dane przez `@include`.
- **Komentarz ścieżki** w 1. linii blade'a Livewire też obowiązuje.
- **Blade ma JEDEN root element** (wymóg Livewire).

---

## 9. Linki, obrazy, assety

- **Linki wewnętrzne: `route()`** (single-locale — **bez** parametru `locale`, np. `route('home')`,
  `url('/')`). Nie hardkoduj ścieżek typu `/admin/...` w treści.
- **Obrazy z uploadu: `Storage::url(...)`** (nie surowa ścieżka — 404 na prodzie). `alt` + `loading="lazy"`.
- **Assety statyczne: `@vite([...])`** w `master.blade.php`.
- **`public/build` i `public/hot` są gitignorowane** — serwer buduje przez `npm run build` (DEPLOY.md).
  Nie commituj artefaktów buildu.

---

## 10. Formularze (publiczne)

- Czat publiczny = **Livewire** (`wire:submit`, `wire:model`, `@error`, throttle w komponencie) —
  nie klasyczny `<form>` POST.
- Panel admina (review) = **Filament** — obsługuje swoje formularze sam, nie piszemy ich ręcznie.
- Klasyczny `<form>` (gdyby zaszedł): `@csrf`, `old()`, `@error()`, throttle na route, honeypot.

---

## 11. Czego NIE robić (zakazy — tu mieszkają błędy)

1. ❌ **Hardkodowane kolory (hex)** w SCSS — zawsze `var(--...)` z `_variables.scss`.
2. ❌ **Przepisywanie partiali szablonu od zera** — używaj źródeł Landii/EliteAdmin.
3. ❌ **Doklejanie importów w `main.scss`** zamiast w `_sections.scss` (obejście).
4. ❌ **Kopiowanie vendor-JS z szablonu** zamiast instalacji przez npm.
5. ❌ **Importowanie `_variables.scss` admina** (nadpisałby paletę Landii).
6. ❌ **Logika biznesowa / zapytania DB w Blade** — Action/komponent dostarcza dane.
7. ❌ **Inline FQCN** (`\App\...`) — importuj przez `@use()`.
8. ❌ **Brak komentarza ścieżki** w 1. linii blade'a.
9. ❌ **Inline-styles do layoutu** — od tego jest SCSS.
10. ❌ **Polskie znaki bez weryfikacji UTF-8** — sprawdź po zapisie.
11. ❌ **Ciche obejścia standardu** — sygnalizuj i uzasadniaj odstępstwa.

---

## 12. Checklist — przed commitem frontu

- [ ] Komentarz ścieżki w 1. linii każdego blade'a?
- [ ] Kolory w SCSS wyłącznie przez `var(--...)` (zero hardkodu hex)?
- [ ] Nowa sekcja zaimportowana w `_sections.scss` (nie w `main.scss`)?
- [ ] Partiale szablonu z licencji (nie przepisane ręcznie)?
- [ ] Libki JS przez npm + import w `app.js` (nie vendor-pliki)?
- [ ] Powtarzalny markup wydzielony do partiala (`_` prefix)?
- [ ] Entry sekcji to `index.blade.php`?
- [ ] Komponent Livewire: cienki, logika w Action, throttle + walidacja?
- [ ] Klasy importowane przez `@use()` (bez inline FQCN)?
- [ ] Linki przez `route()`/`url()`; obrazy uploadu przez `Storage::url()`?
- [ ] Komentarze EN, UI PL, UTF-8 czysty?
- [ ] `public/build`/`public/hot` poza commitem?

~~~~

---

## Zalacznik: docs/BACKEND_CONVENTIONS.md

~~~~markdown
# Konwencje backendu — Laravel 12 / Filament 5 / Livewire 4

> Zaadaptowane z CLAMS (`docs/ALL_INVARIANTS.md`, `docs/FILAMENT5_CHEATSHEET.md`) i KINGS5
> (`docs/ENUMS_AND_FACTORIES.md`), **przepisane pod TEN projekt** (single-site, single-locale, mały).
> Reguły multi-site/RBAC/membership/money/webhooks z CLAMS — **NIE dotyczą nas, pominięte**.
>
> Czytaj przed pisaniem/zmianą kodu PHP. Sekcja 1 (Enums) i 5 (Filament 5 API) są krytyczne —
> tam mieszkały błędy, które kosztowały najwięcej czasu (hardkod zamiast Enumów, złe API wersji).

---

## 0. Stack + version locks (semantyka wersji ma znaczenie)

- **Laravel 12** (nie 10/11). Struktura `bootstrap/app.php`, brak `app/Http/Kernel.php`.
- **Filament 5** (nie 3/4). Namespace’y i sygnatury — patrz §5.
- **Livewire 4** (nie 3).
- **DB:** MySQL/MariaDB, połączenie `mysql`. PHP 8.2 (local) / 8.5 (prod).

**Źródło wiedzy (zasada nadrzędna — Twój ból „źródła wiedzy"):**
- Konwencje biorę **z tego repo** (CLAUDE.md + `docs/`), nie z pamięci o innych wersjach.
- **Nie pewny API → sprawdź, nie zgaduj:** `composer show <pakiet>`, źródło w `vendor/`, oficjalne docs
  wersji. Nie pisz sygnatury „z głowy", jeśli wersja mogła ją zmienić (patrz §5).
- Sprawy dot. modeli AI/Anthropic/OpenRouter → weryfikuj na żywo (skill `claude-api`), nie z pamięci.

---

## 1. ENUMS — JEDNO ŹRÓDŁO PRAWDY (krytyczne — najdroższy błąd)

**Każda wartość statusu / typu / roli / klasyfikacji = Enum w `app/Enums/`.** Nigdy hardkodowany string.

- **Backed enum `string`.** Wartość case’a = wartość zapisywana w DB.
- **Standardowe metody enuma** (wzorzec KINGS5):
  - `label(): string` — etykieta PL do UI
  - `color(): string` — kolor Filamenta (np. `gray`, `info`, `warning`)
  - `icon(): string` — Heroicon (jeśli używane w UI)
  - `static options(): array` — `value => label` dla Filament `Select`
- **Enum ↔ DB 1:1.** Jeśli kolumna jest typu DB ENUM — wartości muszą się zgadzać; zmiana ENUM w DB i
  zmiana PHP Enuma **w tym samym commicie**. Jeśli kolumna `string` + cast — Enum i tak jest źródłem
  dozwolonych wartości.
- **Cast na modelu:** `protected function casts(): array { return ['status' => MessageRole::class]; }`
- **No magic strings:** każdy string używany w >1 miejscu → Enum lub stała `config`. Porównania typu
  `if ($x === 'active')` są zakazane — `if ($x === Status::ACTIVE)`.
- **Przejścia statusów (jeśli dotyczy):** nie `->update(['status' => X])` na ślepo —
  metoda `canTransitionTo(self $new): bool` na enumie + wyjątek, gdy przejście niedozwolone.

```php
// app/Enums/MessageRole.php
enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Użytkownik',
            self::Assistant => 'Asystent',
        };
    }
}
```

> W TYM projekcie enumy pojawią się przy migracjach: rola wiadomości (`user`/`assistant`),
> ocena (`up`/`down`), itp. — od razu jako Enum, nie string.

---

## 2. Mutacje domenowe → klasa Action

- **Logika biznesowa / mutacje** (INSERT/UPDATE/DELETE, wywołanie AI) → `app/Actions/*Action.php`.
- **Controller / Livewire / Filament Resource = cienkie** — tylko wołają Action, nie zawierają logiki.
  (Zgodne z CLAUDE.md: „cienki controller/Livewire — logika w Action".)
- Przykład docelowy: `AskDocs` (woła OpenRouter, zwraca `{answer, link, covered}`) — komponent
  `Chat` tylko ją wywołuje.

---

## 3. Modele

- **`$fillable` zawsze.** Nigdy `$guarded = []` (modele biorą input z Filament/Livewire/API).
- **Casty** przez `casts()` (Laravel 12), w tym enumy i `'hashed'` dla hasła.
- **Relacje** typowane, eager-load tam gdzie pętla (N+1).
- Bez `Repository` / `DTO` / `CQRS` — Eloquent + Scope/Service wystarcza (§7).

---

## 4. Bezpieczeństwo / kod

- **UTF-8 literal** — polskie znaki (ą ę ś ź ż ó ł ń ć) literalnie w kodzie. Nigdy `\u{...}`. Weryfikuj po zapisie.
- **Autoryzacja przed mutacją** — `Gate::authorize(...)` / Policy, gdy są chronione zasoby. Ukrycie
  buttona w UI ≠ zabezpieczenie. (Dla nas lekkie — panel `/admin` chroniony `canAccessPanel`, §5.)
- **Publiczny endpoint → throttle** (`RateLimiter`) — chroni przed kosztem/abuse (czat).
- **Sekrety tylko w `.env`** (`OPENROUTER_API_KEY`) — nigdy w kodzie/gicie.
- **I/O w tle:** ciężkie/wolne I/O (mail, długie zadania, build korpusu) → `ShouldQueue`.
  Wyjątek świadomy: odpowiedź AI w czacie jest synchroniczna (user czeka na wynik) — to OK.

---

## 5. Filament 5 — API (semantyka wersji — Twój ból „złe API")

**To są realne różnice F5 vs F3/F4 — nie pisz API „z pamięci".**

| ❌ Filament 3 (źle) | ✅ Filament 5 (poprawnie) |
|---|---|
| `Filament\Tables\Actions\*` | **`Filament\Actions\*`** (Action, EditAction, DeleteAction, BulkActionGroup…) |
| `Filament\Forms\Components\Section` (Grid/Tabs/Wizard/Fieldset) | **`Filament\Schemas\Components\Section`** (layout → Schemas) |
| `form(Form $form): Form` | **`form(Schema $schema): Schema`** (`use Filament\Schemas\Schema`) |
| `infolist(Infolist $i): Infolist` | **`infolist(Schema $schema): Schema`** |
| `->mountUsing(fn ($form, $record) => …)` | **`->fillForm(fn (Model $record): array => […])`** |
| `assertFormSet()` / `assertFormFieldExists()` | **`assertSchemaStateSet()` / `assertSchemaComponentExists()`** |

- **Pola formularza** (`TextInput`, `Select`, `Toggle`…) **zostają** w `Filament\Forms\Components\*`.
- **User panelu:** model `User` implementuje `FilamentUser` z `canAccessPanel(Panel $panel): bool`
  (na prod ogranicza dostęp; bez tego na nie-local panel może być 403/otwarty). Na local Filament wpuszcza usera.
- **Typy property w klasach bazowych F5:** `$navigationGroup: string|UnitEnum|null`,
  `$navigationIcon: string|BackedEnum|null`, `$view` jest **non-static** (`protected`).

> Nasze Filament Resources: `Questions`, `ApprovedAnswers` (review/curation). Pisane wg powyższego.

---

## 6. Livewire 4

- **Livewire 4, nie 3.** Komponent: klasa `app/Livewire/Nazwa.php` → `view('livewire.nazwa')`
  (auto-discovery). Szczegóły konwencji komponentu/blade — `docs/FRONTEND_CONVENTIONS.md` §8.
- **Cienki komponent**: stan + widok; logika w Action (§2).
- **Walidacja** atrybutami `#[Validate(...)]`; **throttle** na akcjach publicznych (§4).

---

## 7. Granice architektury (YAGNI — nie rozbudowuj bez bólu)

- **Bez nowych katalogów top-level w `app/`.** Trzymaj się: `Actions/`, `Models/`, `Livewire/`,
  `Filament/`, `Policies/`, `Http/`, `Enums/`, `Console/`. Nowy katalog bazowy = decyzja usera.
- **Bez Repository / DTO-library / CQRS.** Eloquent + Scope/Service. „DTO" = Form Request / array.
- **3 podobne linie ≠ abstrakcja.** Nowa warstwa/resolver wymaga: realnego bólu + kosztu utrzymania.

---

## 8. Verify-first — zanim powiesz „gotowe"

- Nie deklaruj „production-ready" na słowo. Sprawdź realnie: `Schema::getColumnListing(...)`,
  `method_exists(...)`, grep po symbolu w repo, `php artisan test` (jeśli są testy).
- Po zmianie w Filament — odpal smoke (CRUD/panel wstaje). Po zmianie w kodzie PL — UTF-8 check.

---

## 9. Czego NIE robić (zakazy — tu były błędy)

1. ❌ **Hardkodowany string statusu/typu/roli** — zawsze Enum (`app/Enums/`).
2. ❌ **Magic string w >1 miejscu** — Enum / config constant.
3. ❌ **Logika/mutacja w Controller/Livewire/Resource** — przenieś do Action.
4. ❌ **`$guarded = []`** — zawsze `$fillable`.
5. ❌ **API Filamenta „z pamięci"** (F3 namespace) — sprawdź §5 / wersję.
6. ❌ **Polskie znaki jako `\u{...}`** — literalnie, UTF-8 czysty.
7. ❌ **Sekret w kodzie** — tylko `.env`.
8. ❌ **Nowy katalog top-level w `app/` / Repository / DTO-lib** bez bólu (YAGNI).
9. ❌ **„Gotowe" bez weryfikacji** (Schema/method_exists/grep/test).

---

## 10. Checklist — przed commitem backendu

- [ ] Wartości statusów/typów/ról przez Enum (zero hardkodu)?
- [ ] Mutacje/logika w Action, nie w Controller/Livewire/Resource?
- [ ] Model ma `$fillable` (nie `$guarded = []`) + casty (w tym enumy)?
- [ ] Filament: namespace’y/sygnatury F5 (§5), nie F3?
- [ ] Publiczny endpoint z throttle? Sekrety tylko w `.env`?
- [ ] UTF-8 czysty (polskie znaki literalnie)?
- [ ] Bez nowych katalogów top-level w `app/` / bez zbędnych abstrakcji?
- [ ] Zweryfikowane realnie (nie „z głowy") przed „gotowe"?

~~~~

---

## Zalacznik: DEPLOY.md

~~~~markdown
# DEPLOY.md — instrukcja dla agenta AI (pierwsze uruchomienie na serwerze nginx)

> Runbook dla **agenta AI**, który stawia ten projekt na **nowym serwerze (nginx + PHP-FPM)**
> po pierwszym `git clone`. Czytaj **całość** przed wykonaniem. Zasady projektu: `CLAUDE.md`.
> Komendy uruchamiaj z katalogu projektu. Wartości w `<NAWIASACH>` uzupełnij dla TEGO serwera.

---

## 0. Kontekst — co stawiasz

Jednostronicowy asystent AI do dokumentacji KINGS (Laravel 12 + Filament 5 + Livewire 4).
- **Front publiczny:** czat (Livewire) — odpowiada wyłącznie z dokumentacji, zwraca link.
- **Panel review:** Filament 5 pod `/admin` (curation odpowiedzi).
- **AI:** Anthropic Claude API, model `claude-haiku-4-5`. Klucz **tylko w `.env`** — NIGDY w gicie.

> ⚠️ **Stan repo:** część funkcji (komenda `chat:build-corpus`, `config/docs.php`,
> migracje `conversations`/`messages`/`approved_answers`) należy do **scaffoldu v1**.
> Kroki oznaczone **[v1]** wykonuj tylko, jeśli te pliki/komendy już istnieją w repo
> (sprawdź: `php artisan list | grep chat:` oraz `ls config/docs.php`). Jeśli ich nie ma —
> pomiń dany krok, reszta setupu działa na obecnym starterze.

---

## 1. Wymagania serwera (zweryfikuj PRZED startem)

```bash
php -v          # PHP >= 8.2 (prod docelowo 8.5). composer.json wymaga "php": "^8.2"
composer -V     # Composer 2.x
node -v         # Node >= 20 (do build assetów); npm
nginx -v        # nginx
php-fpm -v 2>/dev/null || true   # PHP-FPM (osobny pakiet, np. php8.3-fpm)
mysql --version # MySQL 8.4 LTS (prod) — połączenie Laravel: "mysql"
git --version
```

**Wymagane rozszerzenia PHP** (sprawdź `php -m`):
`pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, bcmath, fileinfo, curl, json`

Jeśli czegoś brakuje — doinstaluj zanim przejdziesz dalej (np. Debian/Ubuntu:
`apt install php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-bcmath ...`).

---

## 2. Pobranie kodu (git)

Repo prywatne → użyj **deploy key (read-only)** albo SSH agenta. Przykład:

```bash
cd /var/www                       # albo inny katalog docelowy
git clone git@github.com:tomeknova/chat.git chat
cd chat
```

> Połączenie HTTPS bez tokena nie zadziała — używaj SSH (deploy key) lub skonfiguruj credential helper.

---

## 3. Zależności PHP (produkcyjnie)

```bash
composer install --no-dev --optimize-autoloader --ignore-platform-req=php
```

`--no-dev` pomija narzędzia developerskie. `vendor/` jest w `.gitignore` — to normalne, że pobierasz je tutaj.

> ⚠️ **Dlaczego `--ignore-platform-req=php`:** `composer.lock` jest generowany na PHP 8.2 (local).
> Zalockowana transitywna zależność Filamenta **`openspout/openspout` 4.28.5** deklaruje
> `~8.2 || ~8.3 || ~8.4` i formalnie nie obejmuje **PHP 8.5** (serwer). To konserwatywny górny limit,
> nie realna niezgodność — biblioteka działa na 8.5. Flaga pomija ten check. Żadna wersja openspout
> nie wspiera jednocześnie 8.2 i 8.5 (8.5 dochodzi dopiero w 4.32, która porzuca 8.2), więc trzymamy
> jeden lock dla obu środowisk i pomijamy platform-req na prod. Bez flagi `composer install` odmówi.

---

## 4. Zależności i build frontendu

```bash
npm ci          # instaluje z package-lock.json (powtarzalne)
npm run build   # Vite build → public/build (Tailwind 4 + Vite 7)
```

> Na serwerze prod używaj `npm run build`, NIE `npm run dev`. `node_modules/` jest ignorowane w gicie.

---

## 5. Plik `.env` (sekrety — tworzony RAZ na serwerze)

`.env` **nie jest w repo** (tylko `.env.example`). Utwórz i uzupełnij:

```bash
cp .env.example .env
php artisan key:generate        # ustawia APP_KEY
```

Następnie **edytuj `.env`** i ustaw co najmniej:

```dotenv
APP_NAME="KINGS Docs AI"
APP_ENV=production
APP_DEBUG=false                 # KRYTYCZNE na prod (true ujawnia stack trace)
APP_URL=https://<DOMENA>        # np. https://chat.kings5-docs.mixpost.pl

# Baza — połączenie "mysql" (.env.example ma domyślnie sqlite — ZMIEŃ):
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=chat
DB_USERNAME=<DB_USER>           # dedykowany user, NIE root
DB_PASSWORD=<DB_PASS>

# Sesja/cache/kolejka — domyślnie "database" (wymaga migracji, patrz krok 6).
# Alternatywa na start bez migracji tabel pomocniczych: file.
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# Klucz Anthropic — DODAJ ten wpis (nie ma go w .env.example):
ANTHROPIC_API_KEY=<KLUCZ_API>   # tylko tutaj, NIGDY w kodzie/gicie
```

> ⚠️ Jeśli zostawisz `SESSION/CACHE/QUEUE=database`, a NIE odpalisz migracji (krok 6),
> strona zwróci **500** (brak tabel). Albo zmigruj, albo tymczasowo ustaw te trzy na `file`.

---

## 6. Baza danych

Utwórz bazę `chat` i **dedykowanego usera** (nie root — zasada prod z `CLAUDE.md`):

```sql
CREATE DATABASE chat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '<DB_USER>'@'localhost' IDENTIFIED BY '<DB_PASS>';
GRANT ALL PRIVILEGES ON chat.* TO '<DB_USER>'@'localhost';
FLUSH PRIVILEGES;
```

Migracje:

```bash
php artisan migrate --force     # --force = bez pytania na produkcji
```

> Obecnie migruje tabele bazowe (users, cache, jobs). Po **[v1]** dojdą
> `conversations`, `messages`, `approved_answers` — ta sama komenda je obejmie.

---

## 7. Korpus dokumentacji — `chat:build-corpus`  **[v1]**

Asystent odpowiada z korpusu zbudowanego z markdownów repo **kings5-docs**.

```bash
# 1) udostępnij źródło docs na serwerze (clone obok projektu lub w ustalonej ścieżce):
git clone git@github.com:<ORG>/kings5-docs.git /var/www/kings5-docs

# 2) wskaż ścieżkę źródła docs w .env / config/docs.php (zależnie od implementacji v1),
#    np. DOCS_SOURCE_PATH=/var/www/kings5-docs

# 3) zbuduj korpus:
php artisan chat:build-corpus
```

> Pomiń ten krok, jeśli komenda jeszcze nie istnieje (`php artisan list | grep chat:`).
> Bez korpusu asystent zwróci „brak w docs" — to nie błąd, to fallback.

---

## 8. Konto do panelu Filament

User jest **basic** (standard Laravel — bez dodatkowych pól). Utwórz admina:

```bash
php artisan make:filament-user   # zapyta o name / email / password
```

> Dostęp do panelu na prod może być ograniczony metodą `canAccessPanel()` w `App\Models\User`
> (jeśli zaimplementowana). Na `local` Filament wpuszcza każdego usera.

---

## 9. Cache produkcyjny + linki

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link   # jeśli używane są publiczne pliki ze storage
```

> Po KAŻDEJ zmianie `.env` na prod uruchom ponownie `php artisan config:cache`
> (inaczej zmiany nie wejdą — config jest zcache'owany).

---

## 10. Uprawnienia (web user = `www-data`)

nginx/PHP-FPM działają zwykle jako **`www-data`**. Katalogi zapisywalne:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage -type d -exec chmod 775 {} \;
sudo find storage -type f -exec chmod 664 {} \;
sudo chmod -R 775 bootstrap/cache
```

> Świeży **500 zaraz po wgraniu** = najczęściej brak praw zapisu do `storage/` lub `bootstrap/cache/`.

---

## 11. nginx — server block

DocumentRoot = **`public/`** (nie root projektu). Plik np. `/etc/nginx/sites-available/chat`:

```nginx
server {
    listen 80;
    server_name <DOMENA>;                    # np. chat.kings5-docs.mixpost.pl
    root /var/www/chat/public;               # ZAWSZE katalog public/

    index index.php;
    charset utf-8;

    # Cloudflare/proxy: zachowaj realne IP klienta jeśli za proxy (opcjonalnie).

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        # Dopasuj wersję/ścieżkę socketu PHP-FPM (sprawdź: ls /run/php/):
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Nie serwuj plików ukrytych (np. .env, .git) — ochrona sekretów:
    location ~ /\.(?!well-known).* { deny all; }
}
```

Aktywacja + reload:

```bash
sudo ln -s /etc/nginx/sites-available/chat /etc/nginx/sites-enabled/chat
sudo nginx -t          # test składni — MUSI być OK przed reload
sudo systemctl reload nginx
sudo systemctl reload php8.3-fpm   # dopasuj wersję
```

> **SSL:** docelowo za **Cloudflare Universal SSL** na brzegu (CLAUDE.md). Na origin możesz zostać
> przy `listen 80` (Cloudflare → origin), albo dołożyć cert (Let's Encrypt / Cloudflare Origin Cert)
> i `listen 443 ssl`. Ustal z właścicielem DNS przed włączeniem „Full (strict)".

---

## 12. Smoke test

```bash
curl -I http://<DOMENA>/            # oczekiwane 200 (lub 30x → https)
curl -I http://<DOMENA>/admin       # panel Filament (302 do logowania = OK)
```

W przeglądarce: front czatu odpowiada, `/admin` pokazuje logowanie Filamenta.
Sprawdź też log: `storage/logs/laravel.log` (na świeżym starcie powinien być pusty/krótki).

---

## 13. Aktualizacja / redeploy (kolejne wdrożenia)

```bash
cd /var/www/chat
git reset --hard origin/main          # nadpisz lokalny stan (deploy bez merge-konfliktów)
git pull origin main
composer install --no-dev --optimize-autoloader --ignore-platform-req=php
npm ci && npm run build
php artisan migrate --force
php artisan chat:build-corpus          # [v1] jeśli istnieje
php artisan config:cache route:cache view:cache
sudo systemctl reload php8.3-fpm nginx
```

> `.env` NIE dotykasz przy redeployu (tworzony raz). Po zmianie `.env` ręcznie: `config:cache` ponownie.

---

## 14. Troubleshooting

| Objaw | Najczęstsza przyczyna | Działanie |
|---|---|---|
| **500 zaraz po wgraniu** | brak praw do `storage/` / `bootstrap/cache/` | krok 10 (chown www-data + chmod) |
| **500 + „table not found"** | `SESSION/CACHE=database` bez migracji | `php artisan migrate --force` lub drivery na `file` |
| **502 Bad Gateway** | zły socket PHP-FPM w nginx | sprawdź `ls /run/php/`, popraw `fastcgi_pass` |
| **Zmiana `.env` nie działa** | config zcache'owany | `php artisan config:cache` |
| **Polskie znaki krzaczą się** | złe kodowanie pliku | weryfikuj UTF-8: `grep -cP '[ÃÄÅ]' <plik>` → 0 |
| **AI nie odpowiada / 401** | brak/zły `ANTHROPIC_API_KEY` | sprawdź `.env` (klucz, bez spacji) + `config:cache` |
| **`composer install` odmawia (php constraint)** | `openspout` w lock nie deklaruje PHP 8.5 | dodaj `--ignore-platform-req=php` (patrz krok 3) |

---

## 15. Twarde zasady (z `CLAUDE.md`) — nie łam

- **Sekrety tylko w `.env`** (`ANTHROPIC_API_KEY`, hasło DB) — nigdy w kodzie/gicie.
- **`APP_DEBUG=false`** na produkcji.
- **DB user dedykowany**, nie root.
- **Endpoint czatu = throttle** (RateLimiter) — chroni przed kosztami/abuse.
- **Nginx blokuje pliki ukryte** (`.env`, `.git`) — patrz server block.
- **UTF-8 czysty** w nowych plikach PL.

~~~~

---

## Zalacznik: docs/AUDIT_PACKAGE_GUIDELINES.md

~~~~markdown
# Wytyczne tworzenia pakietów audytu zewnętrznego

> Zaadaptowane z CLAMS (`docs/AUDIT_PACKAGE_GUIDELINES.md`). Treść uniwersalna —
> dostosowane tylko odwołania specyficzne dla CLAMS.

Kanoniczna instrukcja: jak zbudować pliki, które właściciel wkleja do zewnętrznych web‑AI
(min. 3 agenty — np. GPT / DeepSeek / GLM) do review **przed** implementacją (plan/migration
audit — najwyższy ROI) lub **po** (code audit).

**Zasada nadrzędna:** pakiet jest **self‑contained**. Audytor odpowiada *wyłącznie* z tego,
co dostał. Czego w pakiecie nie ma — **zgaduje** (inferencja), a nie weryfikuje. Twoim
zadaniem jest minimalizować pole inferencji: osadzaj nie tylko *konsumentów* zmiany, ale
**samą zmienianą jednostkę i jej kontrakt danych**.

> Dobór agentów + dystrybucja per‑agent (kto co dostaje, mocne strony) — osobny temat,
> do zdefiniowania, gdy zajdzie potrzeba. Ten dokument dotyczy **budowy plików**, nie doboru agentów.

---

## 1. Format pliku

Sprawdzony wzorzec — komenda shell osadzająca ŻYWY kod (nie parafrazę):

```bash
{
  cat <<'HDR'
# Nazwa — AUDIT PART X (warstwa)
> **Stack:** <wersje frameworków — Filament 5 ≠ 3 itd.>  ← ZAWSZE, inaczej audytor founder na złych API
... proza: problem, decyzje, dane ...
HDR
  printf '\n## Załącznik: Foo.php\n```php\n'; cat app/.../Foo.php; printf '\n```\n'
  cat <<'QST'
## Pytania do audytora (per profil + catch-all)
...
QST
} > docs/audits/NAZWA_AUDIT_PACKAGE.md && wc -c docs/audits/NAZWA_AUDIT_PACKAGE.md
```

Heredoc z cudzysłowem (`<<'HDR'`) dla prozy (brak interpolacji `$`), `cat` dla kodu —
gwarantuje realny kod bez błędów transkrypcji.

## 2. Struktura pakietu (sekcje wg warstw)

1. **Stack + zakres** — wersje, typ (plan vs code), runda (co już naprawione → „NIE zgłaszaj ponownie").
2. Schema / migracje.
3. Model + traits.
4. Policy / autoryzacja.
5. Action / service layer.
6. Filament Resource / Pages / Widgets.
7. Pokrycie testowe (istniejące + proponowane).
8. **Pytania do audytora** — stargetowane per profil + catch‑all („znajdź 5 problemów NIE z listy; GO jeśli nic nowego").

## 3. Limit rozmiaru → split

- Oszacuj PRZED generacją: `wc -c <pliki> | tail -1`.
- **>45 KB → podziel na logiczne części** `_PART_A/B/C.md` (np. A: logika/auth, B: migracja/dane, C: testy). Każda część = osobna sesja audytora.
- Każda część <45 KB (margines na nagłówki). Pakiet ma się zmieścić w oknie kontekstu agenta.

---

## 4. ⭐ Kompletność artefaktów — co MUSI być osadzone

To jest **sedno rzetelności**. Najczęstszy błąd: osadzić konsumentów zmiany, ale nie samą
zmienianą jednostkę ani schemat danych — wtedy audytor wykrywa problemy *logiczne*, ale nie
może udowodnić *równoważności zachowania* ani *integralności danych*.

1. **Implementacja ZMIENIANEJ jednostki, nie tylko jej wywołujących.** Jeśli zmiana zależy od
   metody pomocniczej (np. obliczającej zbiór, walidującej, budującej zapytanie) — osadź jej
   **pełną implementację**. Bez niej audytor zakłada zachowanie z komentarzy i nie udowodni, że
   nowa ścieżka daje ten sam wynik co stara.

2. **Schematy DB modeli dotkniętych** — migracje albo `SHOW CREATE TABLE`: kolumny, typy/casty
   (kolumna vs enum‑cast vs scope na relacji), **unique constraints**, indeksy, soft‑deletes,
   **kardynalność relacji** (czy A może mieć wiele B?). Determinuje poprawność fixu (np. czy
   `->sole()` jest właściwe), idempotencję i ryzyko deadlocków.

3. **Pełni konsumenci OSI, której dotyczy zmiana** — nie „lista + 3 przykłady + grep". Jeśli
   zmiana dotyka autoryzacji/bezpieczeństwa, blast radius wykracza poza oczywistych: osadź
   konsumentów **wszystkich osi** (read + write + delete + transfer / capability + visibility).
   Dołącz **wynik grepu po kluczowych symbolach** (metody, enum cases, klasy) **z ich
   callsite'ami**, nie samą nazwę.

4. **Anonimizowany sample danych + macierz per‑item dla migracji.** Statystyki zbiorcze
   („X rekordów, Y dotkniętych") nie wystarczą do udowodnienia poprawności per‑rekord. Dołącz:
   - 3–5 rekordów z **edge‑case'ami** (multi‑relacja, nakładające się zakresy, brakujące rekordy
     zależne, rekord nieaktywny mimo aktywnego rodzica),
   - dla migracji: **matrycę per‑planowana‑zmiana** (kto / co / na jakiej podstawie kwalifikacji),
     żeby audytor mógł wskazać konkretny rekord łamiący założenie.

5. **Pełny executable artefakt, nie pseudokod.** Dla migracji/skryptów osadź **docelową klasę**
   (np. Artisan Command) z transakcją, idempotencją per‑rekord i obsługą błędów — nie samą logikę
   dry‑run. Audytor ocenia to, co realnie pojedzie na danych, nie szkic.

6. **⭐ Contract Verification Script dla zmian BEHAWIORALNYCH.** Gdy zmiana zmienia *zachowanie*
   na istniejących danych (resolver, scoping, kalkulacja), dołącz skrypt porównujący wynik
   **PRZED vs PO** na anonimowym dumpie DB (np. `wynik_old(rekord) === wynik_new(rekord)` dla
   każdego rekordu, z listą rozbieżności). Dla takich zmian to **jedyny** sposób udowodnić
   równoważność / izomorfizm — wszystko inne to inferencja. Najwyższa pojedyncza wartość pakietu.

---

## 5. Deklaracja zakresu + kalibracja pewności

- **Wypisz JAWNIE: pliki ZAŁĄCZONE vs POMINIĘTE** (np. „pełny serwis X — TAK; serwis Y — NIE,
  tylko interfejs"). Audytor wie wtedy, co weryfikuje, a co zgaduje — i nie potraktuje inferencji
  jak dowodu.
- **W pytaniach poproś o oznaczenie każdego findingu:** **CONFIRMED** (wprost z załączonego kodu)
  vs **CONDITIONAL** (wymaga pliku/zapytania, którego nie dostał). Zapobiega awansowaniu
  prawdopodobnych hipotez do „udowodnionych luk".
- **Mini‑diagram zależności** (1 akapit): kto woła zmienianą jednostkę i którą ścieżką
  (whereIn na liście vs per‑record check). Bez tego blast radius opiera się na heurystyce grepu.

---

## 6. Czego NIE robić

- ❌ Osadzać tylko konsumentów, pomijając samą zmienianą metodę/jej kontrakt.
- ❌ Podawać statystyki zbiorcze zamiast sample'a danych z edge‑case'ami.
- ❌ Pseudokod / logikę dry‑run zamiast docelowej klasy executable.
- ❌ Pomijać schemat DB przy zmianach dotykających persistence / migracji.
- ❌ Zmiany behawioralne bez Contract Verification Script.
- ❌ Pakiet bez jawnej deklaracji, czego w nim NIE ma.

---

## 7. Po odpowiedziach audytorów

- Synteza + **grep‑weryfikacja KAŻDEGO findingu** (łapie false‑positives).
- Przy rozbieżności: **adversarial dispute** — drugi agent kontruje finding pierwszego, zanim uznasz go za potwierdzony.
- Dobór agentów, dystrybucja, prompt‑discipline (depth/evidence/GO‑verdict) — osobny dokument, do dodania gdy ustalimy workflow audytowy.

~~~~

---

## Zalacznik: composer.json

~~~~json
{
    "$schema": "https://getcomposer.org/schema.json",
    "name": "laravel/laravel",
    "type": "project",
    "description": "The skeleton application for the Laravel framework.",
    "keywords": ["laravel", "framework"],
    "license": "MIT",
    "require": {
        "php": "^8.2",
        "filament/filament": "^5.6",
        "laravel/framework": "^12.0",
        "laravel/tinker": "^2.10.1"
    },
    "require-dev": {
        "fakerphp/faker": "^1.23",
        "laravel/boost": "^2.4",
        "laravel/pail": "^1.2.2",
        "laravel/pint": "^1.24",
        "laravel/sail": "^1.41",
        "mockery/mockery": "^1.6",
        "nunomaduro/collision": "^8.6",
        "phpunit/phpunit": "^11.5.50"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
    "scripts": {
        "setup": [
            "composer install",
            "@php -r \"file_exists('.env') || copy('.env.example', '.env');\"",
            "@php artisan key:generate",
            "@php artisan migrate --force",
            "npm install",
            "npm run build"
        ],
        "dev": [
            "Composer\\Config::disableProcessTimeout",
            "npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1 --timeout=0\" \"php artisan pail --timeout=0\" \"npm run dev\" --names=server,queue,logs,vite --kill-others"
        ],
        "test": [
            "@php artisan config:clear --ansi",
            "@php artisan test"
        ],
        "post-autoload-dump": [
            "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
            "@php artisan package:discover --ansi",
            "@php artisan filament:upgrade"
        ],
        "post-update-cmd": [
            "@php artisan vendor:publish --tag=laravel-assets --ansi --force"
        ],
        "post-root-package-install": [
            "@php -r \"file_exists('.env') || copy('.env.example', '.env');\""
        ],
        "post-create-project-cmd": [
            "@php artisan key:generate --ansi",
            "@php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\"",
            "@php artisan migrate --graceful --ansi"
        ],
        "pre-package-uninstall": [
            "Illuminate\\Foundation\\ComposerScripts::prePackageUninstall"
        ]
    },
    "extra": {
        "laravel": {
            "dont-discover": []
        }
    },
    "config": {
        "optimize-autoloader": true,
        "preferred-install": "dist",
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true,
            "php-http/discovery": true
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}

~~~~

## Zalacznik: package.json

~~~~json
{
    "$schema": "https://www.schemastore.org/package.json",
    "private": true,
    "type": "module",
    "scripts": {
        "build": "vite build",
        "dev": "vite"
    },
    "devDependencies": {
        "@tailwindcss/vite": "^4.0.0",
        "axios": "^1.11.0",
        "concurrently": "^9.0.1",
        "laravel-vite-plugin": "^2.0.0",
        "sass": "^1.101.0",
        "tailwindcss": "^4.0.0",
        "vite": "^7.0.7"
    },
    "dependencies": {
        "@srexi/purecounterjs": "^1.5.0",
        "aos": "^2.3.4",
        "bootstrap": "^5.3.8",
        "bootstrap-icons": "^1.13.1",
        "glightbox": "^3.3.1",
        "swiper": "^12.2.0"
    }
}

~~~~

## Zalacznik: boost.json

~~~~json
{
    "cloud": false,
    "guidelines": true,
    "mcp": true,
    "nightwatch": false,
    "sail": false,
    "skills": [
        "laravel-best-practices",
        "tailwindcss-development"
    ]
}

~~~~

## Zalacznik: .mcp.json

~~~~json
{
    "mcpServers": {
        "laravel-boost": {
            "command": "php",
            "args": [
                "artisan",
                "boost:mcp"
            ]
        }
    }
}
~~~~

---

## Pytania do audytora

> Oznacz KAZDY finding: **CONFIRMED** (wprost z zalaczonego tekstu) vs **CONDITIONAL** (wymaga pliku/kodu spoza pakietu).

1. **SPOJNOSC:** sprzecznosci miedzy `CLAUDE.md` a `FRONTEND/BACKEND_CONVENTIONS` / `DEPLOY`? Wskaz pare plik:sekcja <-> plik:sekcja.
2. **LUKI:** czego brakuje dla malego L12/F5/LW4, co mimo skali powinno byc (testy, walidacja inputu, obsluga bledow/timeoutow AI, rate-limit, logowanie, prywatnosc)?
3. **ODCHUDZENIE:** czy wyciecie multi-event (templates/{key}, theme::, SectionDataResolver, RBAC, site_id, translacje) usunelo cos potrzebnego single-site? Czy zostalo cos, co single-site NIE dotyczy?
4. **POPRAWNOSC WERSJI:** czy reguly Filament 5 (BACKEND par.5) i Livewire 4 sa zgodne z realnym API tych wersji? (CONDITIONAL, jesli bez pewnosci).
5. **REDUNDANCJA:** nasze reguly vs blok Boost vs `search-docs` — co zostawic, co usunac (problem B wyzej)?
6. **OTWARTE PROBLEMY A-F:** dla kazdego zaproponuj 2-3 warianty rozwiazania z trade-offami. To kluczowa czesc.
7. **CATCH-ALL:** znajdz do 5 problemow NIE z listy (sprzecznosc, ryzyko bezpieczenstwa/kosztu, dwuznacznosc). Jesli nic nowego — **GO**.

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

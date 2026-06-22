# DEPLOY.md — Wdrożenie i konfiguracja serwera (dokument prowadzący dla agenta)

> **Dla kogo:** agent stawiający projekt na serwerze. **Nie czytaj `CLAUDE.md`** — to plik dla agenta
> deweloperskiego (jak budować aplikację). Twoje zadanie: pobrać kod, **wypełnić `.env`**, postawić bazę,
> uruchomić migracje i korpus, ustawić uprawnienia + vhost.
>
> **Źródła:** szablon zmiennych = **`.env.example`** (kanoniczna lista) · baza = **`docs/DB_SETUP.md`** ·
> precedencja/architektura = `docs/AGENT_GUIDE.md`. Ten plik nie powiela tamtych — odsyła do nich.

## 1. Co robi git, a co Ty ręcznie

- **Git przynosi kod ORAZ usunięcia** — `git reset --hard origin/main` (lub `git pull`) usuwa pliki skasowane w commitach
  (np. `config/ai.php` zniknie). `reset --hard` NIE rusza plików nieśledzonych.
- **`.env` jest w `.gitignore`** — git go NIE dostarcza. Wszystkie sekrety i flagi (baza, klucze API, routing AI)
  wpisujesz **ręcznie na serwerze**. Po zmianie `.env` przebuduj cache configu (`php artisan config:cache`).

## 2. Wymagania

- PHP **8.2+** (prod docelowo 8.5). Rozszerzenia: `pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, bcmath, fileinfo, curl, json`.
- **MySQL 8.4 LTS** (połączenie `mysql`; local może być MariaDB). Dedykowany user DB (NIE root).
- Composer, Node (do `npm run build`, jeśli budujesz assety na serwerze; inaczej wgraj `public/build`).
- Opcjonalnie (gdy używasz Bielika): osiągalna **Ollama** z modelem `bielik-11b-v3-q80` (patrz §5).

## 3. Kroki wdrożenia

```bash
# 1. Kod (deploy key, read-only)
git reset --hard origin/main          # lub: git clone … (pierwszy raz)

# 2. Zależności produkcyjne
composer install --no-dev --optimize-autoloader

# 3. .env — pierwszy raz: skopiuj szablon i WYPEŁNIJ (patrz §4)
cp .env.example .env
php artisan key:generate              # ustawia APP_KEY
#   …teraz uzupełnij DB_*, OPENROUTER_API_KEY, OWNER_TOKEN_PEPPER, routing AI itd.

# 4. Baza danych — utwórz bazę `chat` + usera (szczegóły: docs/DB_SETUP.md), potem:
php artisan migrate --force

# 5. Cache produkcyjny
php artisan config:cache route:cache view:cache

# 6. Korpus z dokumentacji (repo kings5-docs musi być pod CORPUS_SOURCE_PATH)
php artisan chat:build-corpus

# 7. Weryfikacja
php artisan chat:assistant-smoke      # kontrakt AI bez ruchu produkcyjnego
```

**Uprawnienia (krytyczne na LAMPP/Apache):** `storage/` i `bootstrap/cache/` muszą być zapisywalne dla usera WWW
(`daemon`/`www-data`). Świeży błąd 500 zaraz po wgraniu = zwykle brak praw do tych katalogów.

**Vhost:** DocumentRoot = katalog **`public/`**. Subdomena docelowa: `chat.kings5-docs.mixpost.pl` (DNS+cert ręcznie).

## 4. `.env` — co MUSISZ ustawić (reszta ma sensowne domyślne; pełny opis w `.env.example`)

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<domena>`, `APP_KEY` (z `key:generate`).
- `APP_TIMEZONE=Europe/Warsaw` (czas polski).
- **Baza:** `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE=chat`, `DB_USERNAME`, `DB_PASSWORD` (→ `docs/DB_SETUP.md`).
- **Sekrety:** `OPENROUTER_API_KEY` (jeśli używasz OpenRoutera), `OWNER_TOKEN_PEPPER` (losowy sekret).
- **Brama:** `AI_ENABLED=true`, `AI_DAILY_BUDGET_USD` (np. 1.0).
- **Korpus:** `CORPUS_SOURCE_PATH` (ścieżka do repo kings5-docs), `DOCS_BASE_URL` (publiczny URL docs).
- **Routing AI:** patrz §5.

## 5. Wybór dostawcy AI (routing)

- **OpenRouter (domyślnie, najprostszy):** `ASKDOCS_PROVIDER=openrouter`, `ASKDOCS_RETRIEVER=full`, ustaw `OPENROUTER_API_KEY`. Koniec.
- **Bielik (lokalny Ollama) + fallback OpenRouter:**
  - `ASKDOCS_PROVIDER=bielik`, `ASKDOCS_FALLBACK=openrouter`, `ASKDOCS_RETRIEVER=lexical` (lexical jest WYMAGANY — pełny korpus przepełnia mały model).
  - Ollama musi mieć model: `ollama pull bielik-11b-v3-q80`.
  - **Ten sam host** co aplikacja: zostaw `BIELIK_BASE_URL=http://localhost:11434/v1`.
  - **Inny host (GPU):** ustaw `BIELIK_HOST=<fqdn>` (adresowanie po NAZWIE, nie IP) + `BIELIK_ALLOWED_CIDR=<sieć/maska>` — endpoint jest rozwiązywany przez DNS, pinowany do IP i sprawdzany względem allowlisty (anty-SSRF) zanim poleci ruch. Adres spoza allowlisty = „down" → fallback.
  - Adres nieosiągalny/niedozwolony nie blokuje czatu: router przechodzi na OpenRouter. Telemetrię prób widać w panelu (Filament → Generations → próby failoveru).

## 6. Aktualizacje (kolejne wdrożenia)

Powtórz kroki 1, 2, 5, 6 (`reset --hard` → `composer install` → `config/route/view:cache` → `chat:build-corpus`).
`migrate --force` uruchamiaj, gdy doszły migracje. `.env` ruszasz tylko, gdy doszły nowe zmienne (porównaj z `.env.example`).

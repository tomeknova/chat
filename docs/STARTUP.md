# STARTUP.md — uruchomienie strony po Fazie 1 (multi-corpus)

> Runbook dla agenta/operatora. Po zmianach Fazy 1 **aktywny korpus to
> `corpus-{CORPUS_PROFILE}.json`** (NIE `corpus.json`). Backend + testy zielone
> (91), wypchnięte na `origin/main`. UI przełącznika to dopiero Faza 2 — tu chodzi
> wyłącznie o **poprawny start istniejącej strony**.

## Co się zmieniło (wpływ na start)

- **Aktywny plik korpusu:** `storage/app/corpus/corpus-{profil}.json` (domyślnie
  `corpus-kings5-docs.json`). Stary `corpus.json` = **tylko legacy fallback dla KINGS**.
- **`chat:build-corpus`** buduje WSZYSTKIE `enabled` profile + `corpus-master.json`
  (manifest), atomowo, pod lockiem. **Exit ≠ 0**, gdy źródło `enabled`-profilu brakuje
  → na maszynie bez `clams-docs` ustaw `CORPUS_CLAMS_ENABLED=false` (inaczej build pada).
- Po zmianie `.env`/configu: **`config:clear`** (local) lub **`config:cache`** (prod),
  inaczej runtime widzi stary config korpusu.
- **Assety bez zmian konwencji:** `public/build` jest gitignored — budowane na serwerze
  (`npm run build`) albo lokalnie. NIE idą przez git.
- Dostawca AI (`ASKDOCS_PROVIDER`/`RETRIEVER` w `.env`, Bielik/OpenRouter) — bez zmian.

## Local (https://chat.test)

1. Repo docs obecne: `/opt/lampp/htdocs/kings5-docs` **i** `/opt/lampp/htdocs/clams-docs`.
2. `php artisan chat:build-corpus`
   → tworzy `corpus-kings5-docs.json` + `corpus-clams-docs.json` + `corpus-master.json`.
3. `php artisan config:clear` (jeśli config był cache'owany).
4. **Front — jedno z:**
   - **HMR:** `npm run dev` (cert mkcert już zaufany w snap Firefoksie).
     Jeśli serwery dev się namnożą (skaczące porty 5173→5174…):
     `pkill -f 'node_modules/.bin/vite'`, potem **jeden** `npm run dev`.
   - **Build:** `pkill -f 'node_modules/.bin/vite'` → `rm -f public/hot` → `npm run build`.
5. Otwórz `https://chat.test/` → **twardy refresh** (Ctrl+Shift+R).

## Serwer testowy / prod

1. `git fetch && git reset --hard origin/main`.
2. **`.env` — krytyczne:**
   - `CORPUS_PROFILE=kings5-docs` (profil domyślny; zmień, by serwować CLAMS).
   - **`CORPUS_CLAMS_ENABLED=false`** — DOPÓKI `clams-docs` NIE jest sklonowane na serwerze.
     Bez tego `chat:build-corpus` zwróci exit ≠ 0 (clams `unavailable`) → `deploy.sh` (`set -e`) padnie.
     Gdy `clams-docs` będzie na serwerze: usuń tę flagę + ustaw `CORPUS_CLAMS_SOURCE`
     (ścieżka repo) i `DOCS_CLAMS_BASE_URL` (domena docs CLAMS).
   - Stare `CORPUS_SOURCE_PATH`/`DOCS_BASE_URL` dalej działają jako wartości profilu KINGS.
3. `composer install --no-dev --optimize-autoloader`
4. `php artisan config:cache route:cache view:cache`
5. `php artisan migrate --force`
6. `php artisan chat:build-corpus` (zbuduje `enabled` profile + manifest)
7. `npm ci && npm run build` (serwer odbudowuje assety — Node dostępny).
   **`public/hot` NIE może istnieć w prod** (inaczej strona celuje w dev server).
8. **Weryfikacja:** `curl -I https://<domena>/` = `200`; strona stylowana; asystent odpowiada
   i wskazuje źródło.

## Szybka diagnostyka

- **„Strona goła / bez stylów":** `public/hot` istnieje, a dev server nieosiągalny/cert
  niezaufany → `rm -f public/hot` + `npm run build` (albo napraw cert dev / zatrzymaj zbędne `vite`).
- **„Asystent nic nie wie / pusty korpus":**
  ```bash
  php artisan tinker --execute 'var_dump(config("corpus.active_profile"), is_file(config("corpus.output_path")));'
  ```
  Jeśli `false` → `php artisan chat:build-corpus` (+ `config:clear`/`config:cache`).
  Status per profil: `storage/app/corpus/corpus-master.json` (`ok` / `stale` / `unavailable` + `reason`).
- **Deploy padł na build-corpus:** brak `clams-docs` na serwerze → `CORPUS_CLAMS_ENABLED=false` (patrz wyżej).
- **Testy NIGDY nie ruszają realnej bazy** — biegną na sqlite `:memory:` (wymuszone w `tests/TestCase.php`).

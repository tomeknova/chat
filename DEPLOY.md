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

# DB_SETUP.md — runbook dla agenta AI: postawienie bazy danych (projekt `chat`)

> Cel: udostępnić aplikacji gotową bazę. Połączenie Laravela: **`mysql`** (przenośne:
> local = MariaDB/LAMPP, prod = **MySQL 8.4 LTS**). Baza: **`chat`**.
> Tabele tworzą **migracje** (`database/migrations/`) — **NIE twórz tabel ręcznie**.
> Pełny deploy serwera: `DEPLOY.md`. Ten plik dotyczy wyłącznie bazy.

## 0. Co masz osiągnąć (definicja „gotowe")
- Istnieje baza `chat` (utf8mb4) + użytkownik z prawami do niej.
- `.env` wskazuje na tę bazę (połączenie `mysql`).
- `php artisan migrate:status` pokazuje **wszystkie migracje = Ran**:
  Laravel: `users`, `cache`, `jobs`, `sessions` · aplikacyjne: `conversations`,
  `messages`, `generations`, `generation_context`, `message_units`.
- Istnieje konto do panelu `/admin` (Filament).

## 1. Wymagania (zweryfikuj PRZED startem)
- **MySQL 8.4** (prod) lub **MariaDB** (local LAMPP). Sprawdź: `mysql --version`.
- Rozszerzenia PHP: `pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, bcmath, fileinfo, curl, json`
  (`php -m | grep -i pdo_mysql`).
- Kodowanie **utf8mb4** (polskie znaki w treści).

## 2. Utwórz bazę i użytkownika

### Prod (MySQL 8.4 — dedykowany użytkownik, NIE root)
```sql
CREATE DATABASE chat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'chat'@'localhost' IDENTIFIED BY 'WSTAW_SILNE_HASLO';
GRANT ALL PRIVILEGES ON chat.* TO 'chat'@'localhost';
FLUSH PRIVILEGES;
```
> MySQL 8.4 używa `caching_sha2_password` — `pdo_mysql` (PHP 8.x) to obsługuje, nic nie zmieniaj.
> Prawa zawężone do `chat.*` (nie globalne). Root tylko do założenia bazy.

### Local (LAMPP / MariaDB — dev, root bez hasła)
```sql
CREATE DATABASE IF NOT EXISTS chat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
```bash
# LAMPP zwykle: socket /opt/lampp/var/mysql/mysql.sock, user root bez hasła
/opt/lampp/bin/mysql -u root -e "CREATE DATABASE IF NOT EXISTS chat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

## 3. Skonfiguruj `.env` (sekrety — NIE w gicie)
`.env.example` ma domyślnie `sqlite` — **zmień na `mysql`**:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=chat
DB_USERNAME=chat            # local dev: root
DB_PASSWORD=WSTAW_SILNE_HASLO   # local dev: puste
```
Sesja/cache/kolejka są na `database` (wymagają tabel `sessions`/`cache`/`jobs` — tworzą je migracje w kroku 4).

## 4. Migracje — utwórz tabele
```bash
php artisan migrate --force
```
Weryfikacja:
```bash
php artisan migrate:status      # wszystko [Ran]
```
> Kolejność FK jest zapewniona timestampami migracji (conversations → messages → generations →
> generation_context → message_units). Wszystkie FK to `ON DELETE CASCADE`.

## 5. Konto do panelu Filament (`/admin`)
```bash
php artisan make:filament-user
# podaj: name, email, password
```
> Model `User` implementuje `FilamentUser` (`canAccessPanel` = true), więc każdy użytkownik z tabeli
> `users` ma dostęp do panelu. Brak publicznej rejestracji — konta zakłada admin tą komendą.

## 6. (po DB) Korpus dokumentacji
```bash
php artisan chat:build-corpus
```
> Wymaga źródła docs pod `CORPUS_SOURCE_PATH` (domyślnie `/opt/lampp/htdocs/kings5-docs`).
> Korpus będzie **pusty**, dopóki strony docs nie mają frontmatter `assistant: true` (bezpieczny
> default). Szczegóły: `kings5-docs/docs/AUTHORING_FOR_ASSISTANT.md`.

## 7. (opcjonalnie, local) Parytet schematu na MySQL 8.4 — Docker
Aby przetestować schemat na silniku prod bez ruszania LAMPP:
```bash
docker run -d --name chat-mysql84 -e MYSQL_ALLOW_EMPTY_PASSWORD=yes -e MYSQL_DATABASE=chat -p 3307:3306 mysql:8.4
# poczekaj aż gotowy: docker exec chat-mysql84 mysqladmin ping -h localhost --silent
DB_HOST=127.0.0.1 DB_PORT=3307 DB_PASSWORD= php artisan migrate:fresh --force
docker rm -f chat-mysql84      # sprzątanie (LAMPP nietknięty)
```
> (Compose v2 może nie być zainstalowany — `docker run` wyżej nie wymaga Compose.)

## 8. Weryfikacja końcowa
```bash
php artisan migrate:status                                  # wszystko Ran
php artisan tinker --execute 'DB::connection()->getPdo(); echo "DB OK\n";'
```
- Zaloguj się na `/admin` kontem z kroku 5 → widoczne zasoby „Pytania i odpowiedzi" + „Generacje".

## Pułapki (tu giną agenci)
- ❌ `DB_CONNECTION=sqlite` (domyślne w `.env.example`) — **musi być `mysql`**.
- ❌ Ręczne `CREATE TABLE` — tabele tylko przez `php artisan migrate` (kontrakt schematu).
- ❌ Prod na root — użyj dedykowanego usera z prawami tylko do `chat.*`.
- ⚠️ utf8mb4 (inaczej polskie znaki się psują).
- ⚠️ `storage/` + `bootstrap/cache/` muszą być zapisywalne dla usera WWW (świeży 500 ≠ błąd DB).
- ⚠️ Reset całej bazy w dev: `php artisan migrate:fresh` (DROP + ponowne migracje) — **nigdy na prod z danymi**.

## Powiązane
- Pełny deploy serwera (nginx, PHP-FPM, cache, vhost): `DEPLOY.md` (uwaga: sekcje AI w DEPLOY.md
  są częściowo **sprzed pivota** — aktualny provider to **OpenRouter**, klucz `OPENROUTER_API_KEY`,
  model `openai/gpt-5.4-nano`; korpus przez `chat:build-corpus`).
- Schemat/enumy: `docs/SCOPE_V1.md`; model danych = 5 tabel z sekcji „Model danych".

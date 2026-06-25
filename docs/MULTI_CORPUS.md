# MULTI_CORPUS.md — roadmap v1.0 (wdrożeniowa) · dwie instrukcje: kings5-docs + clams-docs

> **STATUS: DOJRZAŁA — gotowa do wdrożenia.** Implementacja w Fazach 1–3 (na końcu).
> Kod rusza na **jawne „GO" per Faza** (konwencja: bez zmian w kodzie do GO). Po wdrożeniu
> Fazy sekcja „STAN IMPLEMENTACJI" dostaje mapowanie → commity.
>
> **Historia:** v0.1 profile + manifest; v0.2 przełącznik UI + reset + kolumna `profile`;
> v0.3 audyt #1 (tania higiena, odrzucony przerost enterprise); v0.4 audyt #2 (fallback KINGS-only,
> trzy momenty aktywacji, stany buildu, globalny lock, kontrakty exit/deploy); v0.5 `messages.profile`
> (profil = część tożsamości pytania); **v1.0 audyt #3** — inwariant trzech kopii profilu, table-first
> = indeks KANDYDATÓW (nie cache odpowiedzi), `QuestionNormalizer v1`, „Nowa rozmowa"/switch = wyczyść okno + zachowaj historię
> (bez hard delete), kontrakt cookie, ID profilu immutable, domknięcia build/deploy/lock/provenance.
> Przejście z roadmapy **audytowej** na **wdrożeniową**.
>
> **Cel:** ten sam asystent (`chat`) obsługuje **dwie** instrukcje — `kings5-docs` i `clams-docs` —
> ale w danym momencie pracuje na **dokładnie jednej** (aktywnej). Build odświeża **oba** korpusy
> (wymóg crona) i spina je manifestem. **Wiążące:** `docs/SCOPE_V1.md` + `docs/KICKOFF_V1.md`
> (grounded WYBÓR answer-unit, walidacja `∈ generation_context` + `content_hash`; „korpus = plik,
> NIE version tables"). Kontrakt strony docs identyczny w obu repo.

## Zasada (inwarianty)

- **Profil korpusu** = nazwana instrukcja: `kings5-docs`, `clams-docs`. W danym momencie aktywny jeden.
- **Jeden request → jeden korpus.** `generation_context` to immutable snapshot → brak kolizji między profilami.
- **Jedna rozmowa → jeden profil** (`Conversation.profile` praktycznie niezmienny po utworzeniu).
- **Profil = część tożsamości pytania** — `messages.profile` (snapshot z rozmowy) + indeks
  `(profile, normalized_question_hash)`; to samo pytanie w KINGS i CLAMS to **różne** klucze.
- **Build odświeża wszystkie profile** do osobnych plików + master, pod globalnym lockiem.
- **Przełącznik = UI (runtime).** `CORPUS_PROFILE` = profil domyślny (start gościa / fallback).
- **Dane per profil są data-driven w configu** — jeden tor kodu, sparametryzowany aktywnym profilem.

## Model danych

- **`conversations.profile`** — **źródło prawdy domenowej**. Niezmienny po utworzeniu rozmowy.
- **`messages.profile`** — **niezmienny snapshot kopiowany z `Conversation.profile` przy zapisie**,
  zawsze **po stronie serwera** (`$message->profile = $conversation->profile`) w **jednej** akcji
  zapisującej wiadomość. **Nigdy** z publicznego `$profile`, requestu, frontendu ani `config('corpus.default')`.
  Inwariant: `message.profile === message.conversation.profile` (test; dotyczy wiadomości user/asystent/
  ewentualnych systemowych/powitań, jeśli zapisywane jako rekordy).
- **`generations.metadata.corpus_profile`** — niezmienny snapshot profilu operacji AI (+ `corpus_built_at`).
- **Backfill** `messages.profile` **kopiuje z rozmowy** (`= conversations.profile`), nie ustawia hurtem
  `kings5-docs` (nie uzależniać migracji od założenia czasowego). Migracja: `nullable` → backfill → `NOT NULL`.
- **Enum** `App\Enums\CorpusProfile` (`Kings5Docs`, `ClamsDocs`) + cast; bez natywnego MySQL ENUM.
- **ID profilu = trwały kontrakt domenowy (immutable).** Można zmienić `label`, **nie** `value`. Wycofanie:
  `enabled=false` — **nigdy** usunięcie przypadku enum / configu ani rename (`clams-docs`→`clams`); historyczne
  profile zostają w enumie i configu (choćby wyłączone), inaczej dane historyczne rzucą enum/config error.
- **`normalized_question_hash`** — `QuestionNormalizer v1` (nazwany, pokryty testami): trim, redukcja
  wielokrotnych spacji, normalizacja końców linii, wielkość liter, interpunkcja, normalizacja Unicode.
  Wersja zaszyta w danych hasha: `sha256("v1\0".$normalized)`. To **exact normalized match**, **nie**
  dopasowanie semantyczne („Jak dodać członka?" i „W jaki sposób utworzyć członka?" = różne hashe — OK).
  Algorytmu nie zmieniać bez świadomego przeliczenia hashy. W Filamencie `profile`/`normalized_question_hash`
  są **read-only** (filtr tak, edycja nie — zniszczyłaby spójność).

## Pliki wyjściowe (intuicyjne nazwy)

W `storage/app/corpus/`: `corpus-kings5-docs.json`, `corpus-clams-docs.json`, `corpus-master.json`
(manifest spinający). Stary `corpus.json` — patrz „Fallback i okno deployu" (fallback **tylko KINGS**).

## Manifest (`corpus-master.json`) — indeks build-side

Czas **UTC (`Z`)**. Zapis **atomowy** (`*.tmp` + `rename` w tym samym katalogu/FS), jak każdy `corpus-*.json`.
Przy `--profile` master jest **wczytywany i scalany** — wpisy pozostałych profili i ich `artifact_built_at` nietknięte.

```json
{
  "schema_version": 1,
  "updated_at": "2026-06-25T18:00:00Z",
  "last_full_build_at": "2026-06-25T18:00:00Z",
  "default": "kings5-docs",
  "profiles": {
    "kings5-docs": {
      "file": "corpus-kings5-docs.json", "source": "/opt/lampp/htdocs/kings5-docs",
      "units": 42, "status": "ok", "reason": null,
      "artifact_available": true, "artifact_built_at": "2026-06-25T18:00:00Z", "last_attempt_at": "2026-06-25T18:00:00Z"
    },
    "clams-docs": {
      "file": "corpus-clams-docs.json", "source": "/opt/lampp/htdocs/clams-docs",
      "units": 7, "status": "stale", "reason": "missing-source",
      "artifact_available": true, "artifact_built_at": "2026-06-24T18:00:00Z", "last_attempt_at": "2026-06-25T18:00:00Z"
    }
  }
}
```

- `status`: `ok` / `stale` (build nieudany, ale jest poprzedni dobry) / `unavailable` (nieudany i brak artefaktu).
- `reason`: `null` / `missing-source` / `build-failed` / `invalid-output`.
- `updated_at` — każdy zapis; `last_full_build_at` — **tylko gdy pełny build, w którym wszystkie `enabled`
  profile = `ok`** (inaczej monitoring uznałby niepełny komplet za świeży).
- **Last-known-good:** build OK → nowy artefakt; build błąd, jest poprzedni → `stale` + `reason`, **stary plik bez zmian**;
  build błąd, brak artefaktu → `unavailable`.

## Komenda `chat:build-corpus`

- **Bez argumentów** (cron/deploy) → wszystkie profile + master. `--profile=X` → tylko X (scala master).
- **Walidacja PRZED `rename`:** JSON się dekoduje, schema OK (wymagane pola), `units > 0` (zero przy niepustym
  źródle → `invalid-output`). Dopiero po walidacji `tmp → rename`. Porażka → **nie nadpisuj** starego pliku;
  master = `stale`/`unavailable` + `reason`. Pozostałe profile budują się dalej.
- **Cleanup `*.tmp` w `finally`** (wyjątek parsera / nieudana walidacja / błąd `rename` / przerwanie) — bez
  gromadzenia plików tymczasowych.
- **Owner/prawa po publikacji:** `rename` zachowuje plik utworzony przez proces buildu (deploy/cron/admin),
  a runtime to często **inny** użytkownik (Apache/PHP). Sprawdzić owner/group/umask i prawa katalogu
  `storage/app/corpus` + pliku po `rename`, by runtime mógł czytać.
- **Exit code = dla ŻĄDANEGO zestawu profili:** bez `--profile` — exit 0 gdy wszystkie `enabled` = `ok`;
  `--profile=X` — exit 0 gdy **X** = `ok` (nie patrzy na inne). Profil świadomie nieobecny na maszynie → `enabled=false`.
- **Globalny lock (jeden klucz dla wszystkich wariantów — wspólny master):** klucz `chat:build-corpus`;
  `Cache::lock('chat:build-corpus', 600)`; przy zajętym locku **od razu kontrolowany komunikat + exit ≠ 0**
  (nie czeka); zwalnianie w `finally`/`->get(Closure)`. Scheduler: `->withoutOverlapping(15)` (jawny czas,
  nie domyślne 24h). **Bramka deployu:** produkcyjny `CACHE_STORE` musi współdzielić stan między procesami CLI
  (`file`/`database`/`redis`, nie process-local `array`).
- **Atomowość obejmuje też `corpus-master.json`.** `BuildCorpus` (akcja) — **jawny profil/target** (bez mutacji
  globalnego configu): buduje payload + pisze plik tymczasowy. Publikację (`rename`) + scalenie mastera robi
  **jedno** miejsce (komenda) — bez podwójnej odpowiedzialności za atomowość.

## Runtime + przełącznik instrukcji w UI

Nagłówek czatu: „Asystent dokumentacji" + przyciski profili (iteracja po **dostępnych** z configu):

```
Asystent dokumentacji      [✓ KINGS]  [ CLAMS ]      [↻ Nowa rozmowa]
```

- Aktywny: klasa `is-active` + zielony `bi-check-lg`. **A11y:** `type="button"`, `aria-pressed`, tekstowe oznaczenie stanu.
- **Double-click:** `wire:loading.attr="disabled"` + `wire:target="switchProfile"` blokuje przyciski na czas requestu.

### „Dostępny profil" (definicja — czytelny, nie tylko „istnieje")
1. `CorpusProfile::tryFrom($name) !== null`; 2. istnieje w `config('corpus.profiles')`; 3. `enabled === true`;
4. artefakt **czytelny** — przy pierwszym faktycznym odczycie retriever robi `is_readable` + dekod JSON +
minimalna schema; uszkodzony/zerowy/zły JSON → `unavailable` (nie tylko `is_file`). Dla KINGS dozwolony legacy fallback.
Runtime nie używa mastera → brak/uszkodzenie artefaktu wykrywa **resolver ścieżki**. `stale` z czytelnym artefaktem
→ dostępny. Brak artefaktu (poza KINGS) → przycisk `disabled`/ukryty, **bez** podstawiania innego korpusu i **bez**
tworzenia rozmowy. Jeśli artefakt znika **po** otwarciu rozmowy → `sendMessage()` **przerywa przed** zapisem pytania
jako operacji, utworzeniem generacji i wywołaniem providera.

### Cykl życia aktywacji profilu (trzy momenty)
Jedna prywatna metoda `activateConversationProfile()` (ustala profil z `Conversation.profile` lub `default`,
woła `applyProfile()`), wołana w:
- **`boot()`** — każdy zwykły request;
- **`mount()`** — po odtworzeniu rozmowy z cookie (na 1. requeście `boot()` mógł odpalić się zanim `conversationId`
  zostało ustawione → bez tego linki/greeting użyłyby domyślnego profilu);
- **`switchProfile()`** — po utworzeniu i ustawieniu nowej rozmowy (`boot()` aktywował jeszcze STARY profil).

`applyProfile($name)` → `config(['corpus.output_path'=>…, 'corpus.base_url'=>…, 'corpus.suggestions'=>…, 'corpus.greeting'=>…])`.

### Bezpieczeństwo i kontrakt rozmowy
- **`#[Locked]` na `$conversationId` i `$profile`** (publiczne właściwości Livewire są mutowalne po stronie klienta;
  `$profile` jest tylko prezentacyjny — źródło prawdy = `Conversation.profile`).
- `switchProfile($name)` waliduje `$name` wg „Dostępny profil" — **nie ufa wartości z klienta**.
- **Kontrakt cookie:** aktywna rozmowa = **wskazana ważnym cookie i należąca do `owner_token_hash`**. Nieważne/cudze/
  usunięte cookie → **nowa rozmowa w profilu domyślnym** (nie „najnowsza rozmowa właściciela" — to inny kontrakt,
  niewybierany dopóki nie powstanie ekran historii).

## Semantyka rozmów: switch i „Nowa rozmowa" = wyczyść okno, zachowaj historię

Zasada (decyzja usera): **przejście do nowej rozmowy czyści samo OKNO czatu; historia zostaje zapisana w bazie.**
Brak twardego kasowania rekordów — i bez osobnego przycisku „Usuń rozmowę".

- **`switchProfile`** — tworzy **nową** rozmowę w nowym profilu (`startNewConversation(profile)`), ustawia
  `conversationId` + cookie, czyści okno (świeże powitanie/startery nowego profilu). Stara rozmowa **zostaje w bazie**.
- **„Nowa rozmowa"** — analogicznie: nowa rozmowa w **tym samym** profilu, okno wyczyszczone; stara **zostaje w bazie**.
  (Zmiana wobec dotychczasowego `resetChat()`, który robił hard delete — teraz **nie kasuje** rekordów.)

> **RODO — pokryte bez przycisku usuwania.** Skoro historia ma być zapisana, a okno tylko czyszczone: (a) PII jest
> **redagowane przed zapisem** (`RedactPii`, „surowe pytanie NIE jest trzymane"), (b) erasure pozostaje zdolnością
> **administracyjną** (kasowanie po `owner_token_hash`), nie nowym przyciskiem usera. Dane kuracji
> (feedback/ratingi/generacje) **nie giną**.

**Rozmowy historyczne** są **zachowywane (historia), niedostępne z UI użytkownika** (okno jest czyszczone, brak ekranu
historii). Wymaga to **przyszłej polityki retencji** (jak długo trzymać rozmowy bez feedbacku, czy usuwać puste, czy
oceniane trzymać dłużej, czy obecny cleanup to obejmuje) — inaczej częsty switch = nieograniczone gromadzenie.

## Przyszły kierunek: lookup pytań przed AI (table-first)

> **NIE budujemy teraz** (SCOPE_V1 odkłada tabelę odpowiedzi; YAGNI). Tu zapewniamy wyłącznie **forward-compat**:
> profil jest częścią klucza pytania (`messages.profile` + indeks). Sama tabela lookupu/cache — później.

`(profile, normalized_question_hash)` to **indeks KANDYDATÓW** (filtr kuracji, znalezienie podobnych historycznych
pytań), **nie** klucz gotowej, bezpiecznej odpowiedzi produkcyjnej. Dlatego docelowy przepływ:

```
profil + znormalizowane pytanie
  → znajdź kandydatów
  → wybierz odpowiedź ZATWIERDZONĄ REDAKCYJNIE (kurator)
  → sprawdź AKTUALNOŚĆ względem źródeł
  → dopiero wtedy zwróć bez AI; w razie braku → AI szuka w korpusie
```

- **👍 użytkownika ≠ zatwierdzenie redakcyjne.** 👍 = sygnał jakości (podnosi priorytet kandydata w panelu); zatwierdzenie
  to **decyzja kuratora**. Inaczej: utrwalenie odpowiedzi przypadkowo poprawnej / nieaktualnej, manipulacja rankingiem,
  cache poisoning.
- **Jedno pytanie = wiele generacji** (pierwsza, retry, po błędzie providera, po zmianie korpusu, kilka ocen). Sam wiersz
  po hashu nie wskazuje kanonicznej odpowiedzi → przyszłe pola decyzji kuracyjnej (`approved_generation_id`,
  `curation_status`, `approved_by`, `approved_at`) — **nie teraz**.
- **Aktualność:** zatwierdzona odpowiedź może zdezaktualizować się po zmianie docs + rebuildzie. Warunek przyszły:
  użyć table-first **tylko gdy nadal aktualna** lub po ponownym zatwierdzeniu (mechanizmy później: zapisane `content_hash`
  answer-unitów, `needs_review`, unieważnianie przy rebuildzie, okresowy przegląd). Bez `build_id`/wersjonowania.

## Fallback i okno deployu

- **Legacy fallback TYLKO dla KINGS.** Gdy `corpus-kings5-docs.json` nie istnieje, a `corpus.json` jest → użyj `corpus.json`.
  Dla **CLAMS brak pliku = `unavailable`**, NIGDY `corpus.json` (inaczej dane KINGS pod profilem CLAMS — złamana izolacja).
  Warunek jawny: `$profile === CorpusProfile::Kings5Docs && ! is_file($newPath) && is_file($legacyPath)`.
- **Legacy a provenance:** stary `corpus.json` bez `built_at` → **nie zmyślać** czasu: `corpus_built_at: null` +
  `corpus_legacy_fallback: true`. Inaczej provenance wygląda dokładnie, ale jest fałszywe.
- **Sekwencja deployu (jawna):** 1) kod z fallbackiem KINGS-only; 2) `config:clear`/`config:cache`; 3) `chat:build-corpus`;
  4) `chat:assistant-smoke --all`; 5) **deploy kończy się błędem, jeśli dowolny `enabled` profil wymagany na tej maszynie
  jest `unavailable`/nie przechodzi smoke** (`enabled=false` nie blokuje); 6) **kolejny release: usuń `corpus.json` + fallback**
  (jawny punkt — „jednorelease'owe" nie może zostać na stałe). Publikacja **per profil** (brak rollbacku całego zestawu —
  KINGS może być opublikowany, CLAMS nie; last-known-good działa niezależnie).

## Pliki do zmiany (inkrement)

1. **`config/corpus.php`** — `profiles` (per profil: `source_path`, `base_url`, `suggestions`, `greeting`, `label`, `enabled`)
   + `default = env('CORPUS_PROFILE','kings5-docs')`; **walidacja defaultu** (zła wartość → czytelny błąd, nie „Undefined array key").
2. **`app/Console/Commands/BuildCorpusCommand.php`** — wszystkie profile + master; `--profile=`; globalny lock (shared store + TTL);
   walidacja przed `rename`; atomowy zapis (w tym master) + cleanup `*.tmp`; scalanie mastera; exit code dla żądanego zestawu.
3. **`app/Actions/BuildCorpus.php`** — jawny profil/target; payload + plik tymczasowy; last-known-good.
4. **`app/Livewire/Chat.php`** — `activateConversationProfile()` w `boot()`/`mount()`/`switchProfile()`; `#[Locked]` na
   `$conversationId` i `$profile`; `startNewConversation()` (switch i „Nowa rozmowa" = nowa rozmowa + wyczyść okno, **bez**
   kasowania rekordów — `resetChat()` przestaje robić hard delete); walidacja `$name`; kontrakt cookie; greeting/startery z
   aktywnego profilu; **`messages.profile` z rozmowy** w akcji zapisu; abort `sendMessage()` gdy profil `unavailable`.
5. **Migracja + enum** — `profile` na `conversations` **oraz `messages`** (backfill **kopiuje z rozmowy**; nullable→backfill→NOT NULL)
   + indeks `messages (profile, normalized_question_hash)`; `App\Enums\CorpusProfile` + cast. `QuestionNormalizer v1` (klasa + testy).
6. **`app/Actions/Corpus/FullCorpusRetriever.php`** — resolver `corpus-{profil}.json`; **czytelność** (is_readable+JSON+schema, nie tylko
   `is_file`); **fallback KINGS-only**; brak/uszkodzenie (poza KINGS) → `unavailable`, bez podstawiania.
7. **`resources/views/livewire/chat.blade.php`** — iteracja po dostępnych profilach; zielony `bi-check-lg`; `type=button`,
   `aria-pressed`; `wire:loading`/`wire:target`; przycisk „Nowa rozmowa" (czyści okno).
8. **`resources/scss/sections/_chat.scss`** — `.chat-profile-switch` + `is-active`; `npm run build`.
9. **`.env` / `.env.example`** — `CORPUS_PROFILE`; źródła/base per profil; stare `CORPUS_SOURCE_PATH`/`DOCS_BASE_URL` jako fallback KINGS.
10. **`app/Actions/AskDocs.php`** — provenance w `generations.metadata` **w chwili tworzenia generacji** (przed providerem):
    `corpus_profile` (z rozmowy) + `corpus_built_at` (z odczytanego payloadu; legacy → `null`).
11. **`chat:eval` / `chat:assistant-smoke`** — `--profile=`/`--all`; exit/wynik dla żądanego zestawu; wypis profilu + `status`.
12. **Filament** — widok kuracji: filtr + kolumna `profile` na `messages`, `profile`/hash **read-only**; widok pokazuje razem
    profil + pytanie + hash + finalną odpowiedź + feedback + źródła/units + czas + status; przy retry — **finalna skuteczna** generacja.
13. **Testy** — „Bramka testów przed GO".

## Decyzje (rozstrzygnięte) + ślad audytów

- **Switch i „Nowa rozmowa" = wyczyść okno, zachowaj historię** (bez hard delete; bez przycisku „Usuń rozmowę"). RODO: redakcja PII + erasure administracyjny.
- **Przycisk publiczny** (przyszły login-gate = warstwa nad asystentem; do tego czasu granica = gate `assistant: true`).
- **Master = indeks build-side (A)** + last-known-good; bez release-manifestu/wersjonowania.
- **Profil = część tożsamości pytania** (`messages.profile` + indeks); table-first = indeks kandydatów, nie cache.
- **Przyjęte z 3 audytów:** fallback KINGS-only; trzy momenty aktywacji; stany `ok/stale/unavailable` + `reason`; globalny lock z konkretem;
  exit/`last_full_build_at`/deploy per `enabled`; `enabled`; walidacja defaultu + hierarchia enum↔config; `#[Locked]` na `$profile`;
  provenance w chwili generacji (legacy null); UTC; cleanup tmp + prawa; czytelność artefaktu; inwariant 3 kopii profilu;
  `QuestionNormalizer v1`; ID profilu immutable; Filament — widok kuracji.
- **Odrzucone (przerost / sprzeczne ze SCOPE_V1):** wersjonowane artefakty + release-manifest + retencja-jako-system-wydań;
  `CorpusRegistry`/`CorpusReleaseResolver`/`CorpusContext`; walidacja czwórką `(profile,build_id,…)`; ostrzeżenia Octane/multi-server;
  `build_id`/`sha256` per generacja; automatyczne `approved` z 👍.

## Pułapki / ryzyka

- **Izolacja w fallbacku** — `corpus.json` wyłącznie KINGS; CLAMS bez pliku = `unavailable`.
- **Lifecycle Livewire** — `boot()` nie wystarcza; aktywacja też w `mount()` i `switchProfile()`.
- **„Plik istnieje" ≠ „dostępny"** — czytelność + JSON + schema; abort przed generacją gdy `unavailable`.
- **config:cache** — `applyProfile()` nadpisuje config per-request (działa z cache); `CORPUS_PROFILE` wymaga `config:cache` po zmianie.
- **Spójność denormalizacji** — `messages.profile` zawsze z rozmowy; nieograniczone gromadzenie rozmów bez retencji.

## Bramka testów przed GO

- `messages.profile` zawsze z rozmowy, nigdy z publicznego stanu; backfill kopiuje `conversations.profile`;
  inwariant `message.profile === conversation.profile`; profil/hash **nieedytowalne** w panelu.
- Legacy fallback **tylko** KINGS; CLAMS bez pliku **nigdy** nie czyta `corpus.json` (→ `unavailable`);
  uszkodzony, ale istniejący JSON → `unavailable`; artefakt znika po otwarciu rozmowy → `sendMessage` przerywa przed generacją.
- Pierwszy render rozmowy CLAMS z cookie używa CLAMS; switch KINGS→CLAMS używa CLAMS **już w tym samym requeście**.
- `$profile`/`$conversationId` ze zmanipulowanego payloadu nie zmieniają korpusu (`#[Locked]`); cudze cookie nie odtwarza rozmowy (owner).
- Nieznany `$name`/`enabled=false` nieselektowalny; zły `CORPUS_PROFILE` → kontrolowany błąd.
- **„Nowa rozmowa" i switch nie kasują rekordów** (stara rozmowa + feedback/generacje zostają w bazie; czyszczone jest tylko okno);
  switch nie zmienia profilu starej rozmowy; double-click zablokowany w UI.
- Nieudany build zostawia poprzedni artefakt → `stale`; bez artefaktu → `unavailable`; `*.tmp` usuwany po błędzie.
- `--profile=X` liczy exit tylko dla X; `last_full_build_at` nie zmienia się po częściowo nieudanym pełnym buildzie;
  `--profile` zachowuje wpisy reszty w masterze; równoległa komenda nie dostaje globalnego locka.
- Legacy KINGS → provenance bez zmyślonego `built_at` (`null`); historyczna rozmowa `enabled=false` nie rzuca enum/config error.
- To samo `normalized_question_hash` w KINGS i CLAMS = różne klucze `(profile, hash)`; indeks **nie** jest UNIQUE (jeden hash, wiele wystąpień).
- 👍 **nie** ustawia automatycznie `approved`; zmiana treści wiadomości zakazana lub wymusza przeliczenie hasha. Działa z `config:cache`.

## Fazy wdrożenia

- **Faza 1 — Build, manifest, izolacja artefaktów (bez UI):** `config/corpus.php` (profiles+default+walidacja);
  `BuildCorpus` (jawny target) + komenda (all+master, lock+TTL+shared-store, walidacja przed rename, cleanup tmp, prawa,
  status/reason+last-known-good, exit per zestaw); `FullCorpusRetriever` (resolver + czytelność + fallback KINGS-only);
  `eval`/`smoke` `--profile`; `.env`. Profil aktywny = `CORPUS_PROFILE`. Testy build/izolacja.
- **Faza 2 — UI + profil per rozmowa + lifecycle:** migracja `conversations.profile` + `messages.profile` + indeks + enum +
  `QuestionNormalizer v1`; `Chat.php` (3 momenty aktywacji, `#[Locked]`, kontrakt cookie, switch/„Nowa rozmowa" = wyczyść okno +
  zachowaj historię, `messages.profile` z rozmowy, abort gdy unavailable); blade (toggle + a11y + double-click); scss. Testy lifecycle/izolacja.
- **Faza 3 — Provenance, kuracja, hardening deployu:** provenance w `generations.metadata` (chwila generacji, legacy null);
  Filament (widok kuracji, read-only profil/hash); sekwencja deployu + punkt usunięcia fallbacku + nota retencji. Table-first
  pozostaje **udokumentowanym kierunkiem** (nie budowany). Testy provenance/kuracja.

## Uwaga o stacku

Decyzja config-driven + `boot()` opiera się na **klasycznym request lifecycle bez długowiecznego procesu** (brak Octane/FrankenPHP),
niezależnie od sposobu podłączenia PHP do Apache (LAMPP może być `mod_php`).

## STAN IMPLEMENTACJI

Nie zbudowane (czeka na „GO" do kodu, per Faza). Po wdrożeniu: mapowanie Faz → commity tutaj.

## Nowe pomysły (do uzupełnienia)

> Sekcja otwarta — miejsce na kolejne pomysły do tej roadmapy.

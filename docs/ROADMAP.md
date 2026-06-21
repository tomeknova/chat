# ROADMAP — AskDocs (asystent AI nad dokumentacja KINGS)

> ⚠️ **SUPERSEDED dla v1.** Wiążące dla v1 = `docs/SCOPE_V1.md` + `docs/BIELIK_INTEGRATION.md`.
> Ten dokument (11 tabel / 14 etapów) = **backlog hardeningu** (gdy ruch urośnie), NIE plan v1.
> Rozbieżności (wiele wierszy `generations`, `config/ai.php`, model `claude-haiku-4.5`) → obowiązuje SCOPE_V1/BIELIK
> (zbudowane: 5 tabel, 1 wiersz generacji + `metadata.attempts[]`, model `openai/gpt-5.4-nano`).
>
> Mapa drogowa wdrozen dla projektu AskDocs. Os glowna = SEKWENCJA ETAPOW
> (bramki weryfikowalne, NIE daty). Szczegoly w sekcji FILARY.
> Wiazacy spec: **docs/AI_ASSISTANT_DESIGN.md v0.5**, kontrakt kanoniczny A-I (sek. 2a).
> Zrodlo konwencji: ten plik + docs/ + CLAUDE.md + docs/BACKEND_CONVENTIONS.md.
>
> **Rewizja pod v0.5** (po 2 audytach generalnych v0.4): walidacja `∈ generation_context` (nie `∈ kandydaci`),
> multi-unit ATOMOWY, BUILD-TIME SECURITY GATE, render `selected_ordinal`, `operation_id`/idempotency,
> rozdzial `model_response_type`/`answerability_status`, provenance-not-relevance, `anyOf`+union wspierane
> z limitami, rozszerzony eval + liczbowe progi selekcji. Zmiany v0.5 oznaczone `[v0.5]`.

---

## 1. Cel, zakres v1, stack

### Cel
Jednostronicowy **pomocnik AI** nad PUBLICZNA dokumentacja panelu KINGS. Uzytkownik
zadaje pytanie -> model odpowiada **wylacznie na podstawie dokumentacji** (grounding =
WYBOR ANSWER-UNIT) + zwraca **link** do wlasciwej strony. Ocena 👍/👎 zasila petle
curation (admin edytuje docs VitePress -> re-index korpusu -> asystent zna poprawna tresc).
Bez fine-tuningu — poprawa w kontekscie.

### RDZEN architektury (kontrakt A-I, v0.5)
- **Grounding = WYBOR ANSWER-UNIT.** Model zwraca `answer_unit_id(s)`; backend renderuje CALA zatwierdzona
  jednostke + link z manifestu. **`[v0.5]` Walidacja `id ∈ generation_context`** (immutable snapshot jednostek
  FAKTYCZNIE w prompcie, NIE `∈ kandydaci`) + `content_hash` czytany Z TEGO snapshotu (nie z live registry).
  `generation_retrieval_candidates` = WYLACZNIE telemetria retrievalu. BRAK verbatim-spanow / `evidence_quote`.
- **`[v0.5]` PROVENANCE + integralnosc renderowanej tresci „by construction"; TRAFNOSC i KOMPLETNOSC = EMPIRYCZNE**
  (mierzone w eval) — answer-units to KONTROLOWANY SELEKTOR zatwierdzonej tresci, NIE generator trafnosci.
- **`[v0.5]` Multi-unit ATOMOWY:** odrzucenie KTOREJKOLWIEK jednostki z zestawu -> `grounding_status=failed`
  (koniec ukrytej czesciowosci). Render wg `selected_ordinal` (kolejnosc w `answer_unit_ids[]`), nie `prompt_ordinal`.
- **Plaska strict json_schema** — `[v0.5]` wybor PROJEKTOWY (prostota, przenosnosc, jawna walidacja domenowa),
  NIE „Anthropic nie potrafi": `oneOf`/`if-then` niewspierane, `anyOf`+union WSPIERANE z limitami (smoke-test per
  trasa). PELNA macierz warunkowosci (wszystkie 4 warianty `response_type`) w deterministycznym walidatorze backendu.
- **`[v0.5]` BUILD-TIME SECURITY GATE** = glowna granica anti-injection: kazda jednostka sklasyfikowana PRZED
  publikacja (`security_verdict` zwiazany z `content_hash`; niejednoznacznosc -> kwarantanna, nie kontekst).
  Runtime output filter = defense-in-depth (maly model POZA deterministycznym rdzeniem walidatora).
- **Fail-closed:** `InvalidSchema` bez naprawy JSON i bez auto-retry; refusal / truncation /
  transport = OSOBNE `InfraStatus`.

### Zakres v1
Czat publiczny + 👍/👎 + petla curation w Filament 5; anon `owner_token` (per-browser,
bez logowania). Retrieval = etap 0 (caly korpus jako kandydaci). Co NIE jest w v1 — sekcja 7.

### Stack (wersje)
- Laravel 12 / PHP **8.2** (local LAMPP) · **8.5** (prod)
- Livewire **4** — publiczny czat (single-page)
- Filament **5** — panel review (curation + telemetria)
- **MySQL 8.4 LTS** (prod) / MariaDB (local) — polaczenie Laravel **`mysql`**, baza `chat`
- AI: **OpenRouter** (OpenAI-compatible), model **`openai/gpt-5.4-nano`**
  (fallback `mistralai/ministral-14b-2512`), klucz `OPENROUTER_API_KEY` (tylko `.env`)
- Tailwind v4; rama Landia + dymki czatu EliteAdmin

### ZASADA SEKWENCJI (nadrzedna)
**NIE zamrazac schematu na papierze.** Najpierw **PROTOTYP PIONOWY** (jeden pelny przeplyw
end-to-end) na **bazie TYMCZASOWEJ**, POTEM zamrozenie **baseline v1**; po v1 **tylko migracje
ADDYTYWNE**. Bramki = komenda/test/metryka, NIE daty.

---

## 2. ZASADY PRZEKROJOWE (checklist KAZDEGO etapu)

| # | Zasada | Egzekucja |
|---|--------|-----------|
| 1 | **Cienki controller / Livewire / Filament Resource** | Logika w klasie **Action** (`app/Actions/`). YAGNI: bez Repository/DTO-library/CQRS. |
| 2 | **Enumy = JEDYNE zrodlo prawdy** | PHP-backed `string`; `label()` PL, `color()`/`icon()` (Filament), `options()`. Zero hardkodu stringow statusu/roli/oceny poza `app/Enums/`. |
| 3 | **Spojnosc kontraktu A-I** | Kazda zmiana zgodna z sek. 2a/6/8/9 spec **v0.5**. Statusy rozlaczne (kontrakt D; `model_response_type` przed walidacja vs `answerability_status` po). |
| 4 | **Sekrety tylko `.env`** | `OPENROUTER_API_KEY`, pepper owner_token — NIGDY w kodzie/gicie. |
| 5 | **Throttle na publicznym czacie** | RateLimiter per-IP (CF-Connecting-IP) + globalny sufit; aktywny TAKZE na sciezce awaryjnej. |
| 6 | **UTF-8 literalnie** | Polskie znaki w `label()` PL bezposrednio (nie `\u{}`). Weryfikacja: `grep -cP '[ÃÄÅ]' plik` == 0. |
| 7 | **Filament 5 API** (nie 3) | `Filament\Actions\*`, `infolist(Schema)`, `form(Schema)`, `Filament\Schemas\Components\*`. Weryfikowac w `vendor/`, nie z pamieci. |
| 8 | **Testy PHPUnit** (`make:test --phpunit`) | Bramka jakosci: warstwa nie jest „gotowa" bez zielonego testu. Bez tinkera tam, gdzie test pokrywa. |
| 9 | **Bez nowych katalogow top-level w `app/`** | Nowy katalog (np. `app/Contracts`, `app/Services`) wymaga jawnego GO usera. Domyslnie `app/Actions/<Domena>/`. |
| 10 | **GO przed kodem** | Bez zmian w kodzie do jawnego „GO"; push tylko na wyrazny sygnal. Konwencja niejasna -> zapytaj. |
| 11 | **Pint** | `vendor/bin/pint --dirty --format agent` czysty przed finalizacja zmian PHP. |

---

## 3. SEKWENCJA ETAPOW (os glowna)

> Per etap: cel, dotkniete filary, BRAMKA. Prototyp pionowy na temp-DB PRZED baseline v1.
> Poprawki KRYTYK-LUKI naniesione (oznaczone `[FIX]`).

### Etap 0 — Decyzje na papierze (BEZ kodu)
**Cel:** zamrozic projektowo (NIE w kodzie) kontrakt A-I, finalny zestaw enumow i szkielet
~11 tabel z 3 strefami cascade. Produkt: `docs/DATABASE_SCHEMA.md` + `docs/ENUM_CATALOG.md` + ADR.
**Filary:** db, corpus, retrieval, client, validator, askdocs, retention, sec-cost, sec-injection.

**`[FIX] Rozstrzygniecia ADR (obowiazkowe w tym etapie):**
- **(a) Liczba i podzial enumow** — `docs/ENUM_CATALOG.md` rozdziela: **9 enumow kanonu sek.8**
  (Role, ProductStatus, AbstentionReason, AnswerabilityStatus, UnitValidationStatus, InfraStatus,
  Rating, ReasonCode + decyzja o GroundingStatus) vs **enumy operacyjne** (ModelResponseType,
  ContextStrategy, CorpusStatus, DraftStatus, SecurityScreeningStatus, RetentionTarget,
  UnitGateExclusionReason, EvalCaseClass, EvalDimension) — te ostatnie WYMAGAJA dopisania do sek.8
  design-doca. **GroundingStatus:** rozstrzygnac — albo enum 2-wartosciowy (Validated|Failed)
  i dopisac do sek.8, albo binarna kolumna VARCHAR(16) liczona w walidatorze bez pliku enum
  (spec linia 567: „grounding_status binarny w v1, agregat"). Liczba „13" zaktualizowana na faktyczna.
- **(b) content_hash vs content_snapshot** — rozroznic DWA byty: `content_hash` (OBOWIAZKOWY,
  zostaje w `generation_context`/`message_units` — podstawa `RejectedHashMismatch`, to NIE jest
  „content_snapshot") vs `content_snapshot` pelnej tresci body (OPCJONALNY, alternatywa dla retencji).
  Wybrac JEDNA strategie retencji: **(opcja A)** korpus_version >= logi (GC z guardem `NOT EXISTS`,
  brak snapshotu body) ALBO **(opcja B)** snapshot body przy generacji (GC korpusu swobodny).
  Filary retention i deploy wskazuja TE SAMA opcje (rekomendacja: opcja A).
- **(c) Telemetria kosztow vs RODO-delete** — rollup do nie-PII / anonimizacja / CASCADE,
  z uzasadnieniem (rekomendacja: **rollup**).
- **(d) ProviderRefusal przez OpenRouter** — dostawca (OpenAI/GPT-5.4-nano) moze zwracac natywny
  `finish_reason: refusal` lub `content_filter` (HTTP200); OpenRouter normalizuje `finish_reason`;
  **mapowanie nativne->InfraStatus w adapterze**.
- **(e) DECYZJA #1 = PUBLICZNY** — `tenant_id`/`user_id` NULLABLE tylko na conversations,
  dodawane addytywnie przy ewentualnym wariancie in-panel.
- **(f) Wlasciciel enumow kolizyjnych** — JEDEN wlasciciel pliku na enum: `CorpusStatus` -> filar
  **corpus** (wartosci zamrozone w ENUM_CATALOG); `InfraStatus` -> jeden plik wspoldzielony,
  filar **client/validator** wlasciciel semantyki parse, **sec-cost** wlasciciel semantyki retry/koszt.
- **`[v0.5]` (g) Uszczelnienia kontraktu z 2 audytow v0.4** (do zamrozenia w DATABASE_SCHEMA/ENUM_CATALOG/spec):
  walidacja `answer_unit_id ∈ generation_context` (snapshot) + `content_hash` z TEGO snapshotu (F-01/F-03);
  PELNA macierz warunkowosci STEP 1 (4 warianty `response_type`, F-04); rozdzial `model_response_type` (z modelu)
  od `answerability_status` (po walidacji, F-06); `message_units.content_hash`/`document_id` **NULLABLE** dla
  `RejectedUnknownUnit` (F-07); `selected_ordinal` w message_units obok `prompt_ordinal` w context (F-10);
  trzy poziomy idempotencji `operation_id`/`request_id`/`provider_request_id` (F-09); PII `raw_question_encrypted`
  + `redacted_question` (decyzja, F-16); owner_token format **WERSJONOWANY** `v<key_version>.<token>` (wybor peppera
  PRZED lookupem); build-time security gate -> pola `security_verdict`/`classified_content_hash` na answer_unit_versions (F-05/F-13).
- **`[v0.5]` (h) Fakty Structured Outputs** (zweryfikowane Anthropic docs): `anyOf`+union WSPIERANE z limitami
  (16 union/zadanie, koszt wykladniczy, timeout 180s, `allOf`+`$ref` nie); plaska schema = wybor projektowy.
  Cache profil **TTL-zalezny** (5min 1.25x / 1h 2x / read 0.1x / min 4096 tok).

**BRAMKA:**
- `docs/DATABASE_SCHEMA.md` istnieje: ~11 tabel (conversations, messages, generations,
  generation_retrieval_candidates, generation_context, message_units, message_sources,
  answer_drafts, corpus_versions, answer_unit_versions) z typami sek.8, 3 strefy cascade
  (CASCADE rozmowa / RESTRICT korpus / SET NULL draft), CHECK jednokierunkowe,
  `selected_generation_id` FK, `public_id` ULID CHAR(26) tylko conversations,
  `owner_token_hash` CHAR(64) + key_version. **`[v0.5]`** + `operation_id`/idempotency_key,
  `selected_ordinal`, `message_units.content_hash`/`document_id` NULLABLE, `raw_question_encrypted`,
  owner_token format `v<ver>.<token>`, `security_verdict`/`classified_content_hash` (build-time gate).
- `docs/ENUM_CATALOG.md` wymienia enumy z wartosciami zgodnymi z kontraktem D (ProductStatus BEZ
  AnsweredPartial; InfraStatus BEZ GroundingFailed; UnitValidationStatus 4 wartosci) — z rozdzialem
  kanon/operacyjne `[FIX-a]` i rozstrzygnieta GroundingStatus.
- ADR zawiera rozstrzygniecia (a)-(f) powyzej.
- **Wlasciciel migracji** ustalony: **filar db = JEDYNY wlasciciel WSZYSTKICH migracji i modeli**
  (w tym answer_drafts, corpus_versions, answer_unit_versions); pozostale filary tylko konsumuja
  modele + dopisuja Action `[FIX duplicate-ownership]`.
- Jawne GO orkiestratora ZAPISANE; zero plikow w `app/Enums`, `app/Actions`, `database/migrations`
  poza szkieletem Laravel.

---

### Etap 1 — Prototyp pionowy na bazie TYMCZASOWEJ (jeden pelny przeplyw E2E)
**Cel:** udowodnic, ze ksztalt tabel obsluguje pelny przeplyw ZANIM cokolwiek zamrozimy:
VitePress -> korpus -> pytanie -> retrieval -> klient -> walidator -> wiadomosc -> feedback,
na bazie TYMCZASOWEJ (migracje WCIAZ edytowalne). Klient i walidator koduja przeciw fake'om/stubom.
**Filary:** db, corpus, retrieval, client, validator, askdocs, ui, curation.
**`[FIX]` rownolegle:** szkielet eval-runnera (stub adaptera + taksonomia + dataset) startuje TU
(kontrakt I: „runner RAZEM z adapterem"), by bramki Etapow 9/10 mialy na czym stanac.

**BRAMKA:**
- **TWARDA:** `php artisan chat:assistant-smoke` exit 0 — realne wywolanie
  `openai/gpt-5.4-nano`: HTTP 200 + cialo parsowalne zgodne z PLASKA strict json_schema
  (`response_type` obecny, `additionalProperties:false`, BEZ `if/then/oneOf`).
- **`[FIX missing-gate]` Cache:** weryfikacja `cache_read_input_tokens>0` przeniesiona do
  **osobnej bramki z fixturem prefiksu >=4096 tok** (syntetyczny korpus paddingowy) — TWARDO
  wymagana w Etapie 1 lub jako warunek NO_GO w Etapie 11. Na fixtures <4096 tok komenda raportuje
  „cache OFF (<4096)" jako STAN, ale to NIE zaspokaja bramki cache (kontrakt G ma egzekwowalna bramke).
- Jeden pelny przeplyw E2E zapisany na temp-DB: pytanie -> FullCorpusRetriever (N kandydatow,
  rank/score=null) -> `generation_retrieval_candidates` + `generation_context` (prompt_ordinal) ->
  AssistantClient -> ValidateGrounding renderuje body accepted -> messages+generations+message_units+
  message_sources atomowo, `content_hash` zgodny z manifestem (weryfikacja zapytaniem).
- ValidateGrounding fail-closed: InvalidSchema bez naprawy/retry (product_status NULL); ProviderRefusal/
  OutputTruncated/TransportInterrupted ROZLACZNE z InvalidSchema; pusty `answer_unit_ids@answer` ->
  InvalidSchema; id spoza kandydatow -> RejectedUnknownUnit; zly hash -> RejectedHashMismatch; 0 accepted -> Abstained.
- Livewire Chat::sendMessage wola WYLACZNIE AskDocs; render escaped plain text; canonical_url tylko
  z manifestu; throttle aktywny; build-corpus na fixtures tworzy corpus_version + manifest_hash,
  idempotencja (ten sam ref -> ten sam manifest_hash).
- Migracje WCIAZ edytowalne — NIE deklarowac baseline.

---

### Etap 2 — Zamrozenie baseline v1 (schema dump + integralnosc)
**Cel:** po przejsciu prototypu ZAMROZIC schemat jako baseline v1: nazwane migracje (kolejnosc wg FK),
CHECK jednokierunkowe + UNIQUE + INDEX (kontrakt E), modele Eloquent (`$fillable` + `casts()` z enumami
+ relacje + `Str::ulid()` w `creating()` tylko Conversation), factory per model, enumy PHP-backed string.
Testsuite **`Database` na MySQL 8.4** (sqlite NIE odda CHECK/cascade/ULID). Odtad: tylko migracje ADDYTYWNE.
**Filary:** db, tests.
**`[FIX ordering]`** minimalna migracja `answer_drafts` (`corpus_version_seen` + `expired`) wchodzi
do baseline TU, aby bramka korpusu (Etap 3) miala realna tabele.

**BRAMKA:**
- `php artisan migrate:fresh` bez bledu na `mysql`; `php artisan schema:dump` utrwala baseline v1; rollback czysty.
- `php artisan test --testsuite=Database` (MySQL 8.4) ZIELONY: CascadeZones (usun conversation ->
  0 sierot we wszystkich tabelach strefy 1; RESTRICT chroni answer_unit_versions; SET NULL na drafcie),
  RegistryVersioning (corpus_versions+answer_unit_versions append-only, re-sync=nowa wersja, stara
  generacja interpretowalna), GenerationRetry (attempt_count, jeden selected_generation_id,
  UNIQUE(message_id,attempt_count)+UNIQUE(request_id)), SchemaIntegrity (UNIQUE/INDEX kontraktu E).
- `php artisan test --filter=SchemaFoundation`: dlugosc kazdego enum->value <= VARCHAR kolumny;
  `Schema::getColumnListing` per tabela; UNIQUE(generation_id,answer_unit_id) blokuje duplikat.
- `ask-docs:audit-enums` exit 0 na czystej bazie, exit!=0 po raw INSERT nielegalnej wartosci;
  grep zero hardkodu statusu/role/rating poza `app/Enums`; `grep -cP '[ÃÄÅ]' app/Enums/*.php` == 0;
  Conversation generuje `public_id` ULID CHAR(26); pint czysty. **BASELINE ZAMROZONY.**

---

### Etap 3 — Korpus: produkcyjny `chat:build-corpus` (gate + walidacja + immutable swap)
**Cel:** dojrzaly fail-closed pipeline na zamrozonym schemacie: ekstrakcja answer-units
(`answer_unit_id` STABILNY != content_hash, body gotowy, intents[]), indeks chunkow-kandydatow,
manifest (canonical_url, host allow-list), **`[v0.5]` 6-warunkowy gate fail-closed** (status/visibility/
ai_enabled/aktywna-wersja/review_after + `security_verdict==pass`), **BUILD-TIME SECURITY GATE**
(klasyfikacja KAZDEJ jednostki PRZED publikacja; `security_verdict`+`classified_content_hash` zwiazany
z `content_hash`; niejednoznacznosc -> KWARANTANNA, nie kontekst), bramka jakosci (build PADA),
publish immutable + atomowy swap + trigger `answer_drafts.expired`.
**Filary:** corpus.

**BRAMKA:**
- `php artisan chat:build-corpus --ref=<pinned>` na fixtures exit 0, tworzy corpus_version +
  manifest_hash, ustawia current_corpus_version (status Published/Active).
- Test gate: jednostka lamiaca KAZDY z **6 warunkow** (w tym `[v0.5]` `security_verdict`) wykluczona
  z `UnitGateExclusionReason`; test walidacji: build PADA przy duplikacie id / pustym body / braku intents /
  zepsutym linku / zduplikowanej kotwicy / braku w manifescie / zniknieciu poprzedniego canonical_url bez redirectu.
- **`[v0.5]` Build-time security gate:** jednostka z instrukcja-injection w body -> `security_verdict=quarantine`
  -> NIE trafia do korpusu/kontekstu; `classified_content_hash == content_hash` (edycja body wymusza ponowna
  klasyfikacje przy re-index); runtime ufa tylko jednostkom `security_verdict==pass`.
- Idempotencja: dwa buildy tego samego ref -> identyczny manifest_hash, COUNT corpus_versions
  niezmieniony; edycja body zmienia content_hash ale NIE answer_unit_id; atomowy swap (--no-swap nie
  rusza current; rollback przestawia wskaznik bez rebuildu).
- **`[FIX ordering]`** trigger `answer_drafts.expired`: poniewaz minimalna tabela istnieje od Etapu 2,
  test jest pelny (draft z `corpus_version_seen < current` -> `expired=true`); zakaz wycieku (artefakt
  NIE w public/ bundlu); pint czysty; `grep -cP '[ÃÄÅ]'` == 0.

---

### Etap 4 — Retrieval: szew CandidateRetriever + budzetowanie tokenow
**Cel:** warstwa retrievalu jako szew abstrakcji (`CandidateRetriever`, lokalizacja uzgodniona z userem
— `app/Actions/Retrieval/`, nie nowy top-level), FullCorpusRetriever (etap 0) czytajacy aktywny
corpus_version po GATE F, RetrievedAnswerUnit VO, konserwatywny TokenEstimator, budzetowanie kontekstu,
binding sterowany ContextStrategy. Szkielet LexicalRetriever bez implementacji.
**Filary:** retrieval.

**BRAMKA:**
- `php artisan test --filter=FullCorpusRetriever` ZIELONY: wylacznie answer-units po GATE F z aktywnego
  corpus_version; retrieval_rank/score=null (etap 0).
- **`[FIX verifiability]`** `php artisan test --filter=TokenEstimator` ZIELONY: estimator testowany
  **deterministycznie na ustalonych probkach z ZNANYM oczekiwanym token_count** (unit test bez sieci;
  konserwatywnosc wzgledem referencyjnego liczenia). Kalibracja wzgledem realnego `usage.prompt_tokens`
  przeniesiona explicite do Etapu 11 (replay) — bramka Etapu 4 NIE wymaga zywej trasy.
- Szew: AskDocs nie referuje konkretnej klasy retrievera — tylko interfejs (grep FullCorpusRetriever
  poza bindingiem/testami == 0); wymiana na fake = sama zmiana bindingu.
- Budzet: suma token_count kandydatow <= `max_input_tokens`; przekroczenie loguje sygnal `corpus_tokens`
  (nie cicho obcina); grep 'FullCorpus'/'Lexical' poza ContextStrategy/config == 0.
- **`[v0.5]` Prog lost-in-the-middle (F-14):** jawny config `corpus_tokens > ~15k` (OSOBNY od progu kosztu)
  wyzwala proaktywny retrieval etapu 1 — chroni telemetrie `answerability_status` przed zatruciem (falszywy `no_match`),
  nie tylko optymalizuje koszt. Prog do kalibracji w Etapie 11.

---

### Etap 5 — Klient AI / OpenRouter: transport + InfraStatus + provider pinning
**Cel:** AssistantClient jako cienka warstwa transportowo-kontraktowa: BuildResponseSchema (plaski strict
z enumow ModelResponseType/AbstentionReason), AssembleAssistantPrompt (SYSTEM zaufany / USER niezaufany
blok + cache_control tylko etap 0), mapowanie 10 rozlacznych InfraStatus, provider pinning
(`only[]`, `allow_fallbacks:false`, `require_parameters:true`, `data_collection:deny`, `zdr:true`;
`models[]` nieuzywany; Response Healing nieuzywany), AssistantCapabilityProfile (provider-agnostic
z eval-gate), BEZ naprawy JSON / auto-retry przy InvalidSchema/refusal/truncation (retry TYLKO timeout/5xx/transport).
**Filary:** client. **`[FIX ordering]`** szkielet eval-runnera/klas eval rozwijany rownolegle (kontrakt I).

**BRAMKA:**
- `php artisan test --filter=AssistantClientTest` ZIELONY: wszystkie sciezki InfraStatus
  (Completed/OutputTruncated/ProviderRefusal/InvalidSchema/ProviderUnavailable/ProviderTimeout/RateLimited)
  rozlaczne; BRAK auto-retry przy InvalidSchema/refusal/truncation (Http==1) ORAZ retry przy 5xx/timeout (>1).
- `php artisan test --filter=ResponseSchemaTest` ZIELONY: schemat PLASKI (zero if/then/oneOf/anyOf/
  minItems/pattern), `additionalProperties:false`, enumy schematu == ModelResponseType/AbstentionReason::cases().
- Asercja configu w body: `provider.only[]`, `allow_fallbacks:false`, `require_parameters:true`,
  `data_collection:'deny'`, `zdr:true`; `models[]` NIEOBECNY; brak flagi Response Healing.
- grep zero literalow statusow poza enumami; klucz API tylko `env()`; pint czysty; UTF-8 czysty.

---

### Etap 6 — Walidator groundingu: deterministyczny rdzen anty-halucynacyjny
**Cel:** ValidateGrounding jako deterministyczny Action STEP 0-5 (jedyne zrodlo prawdy o statusach):
**`[v0.5]`** rozlaczne osie — `model_response_type` (z modelu, przed walidacja) vs `answerability_status`
(po walidacji), oraz InfraStatus, grounding_status, product_status, abstention_reason; **`[v0.5]` STEP 1 =
PELNA macierz warunkowosci** (4 warianty `response_type`: pola wymagane/zabronione + limity MAX_UNITS/
MAX_OPTIONS/dlugosc/duplikaty); **`[v0.5]` walidacja `answer_unit_id ∈ generation_context`** (immutable snapshot)
+ `content_hash` Z TEGO snapshotu (NIE live registry); werdykty per jednostka (UnitValidationStatus);
**`[v0.5]` multi-unit ATOMOWY** (czesciowa akceptacja -> `grounding_status=failed`); OutputInjectionFilter
(regex-only baseline = **defense-in-depth**; glowna granica anti-injection = build-time security gate, Etap 3),
renderer escaped plain-text multi-unit **wg `selected_ordinal`** z canonical_url z manifestu.
Model nigdy nie jest sedzia wlasnego groundingu.
**Filary:** validator.
**`[FIX risk]`** fail-mode klasyfikatora ML (warstwa 2 STEP 3) okreslony w ADR: gdy maly model
niedostepny -> w v1 stub regex-only (degradacja); decyzja fail-OPEN (dostepnosc) vs fail-CLOSED
(odrzut jednostki) zapisana jawnie. Awaria pomocniczego modelu nie zlewa sie z InvalidSchema.

**BRAMKA:**
- `php artisan test --filter=ValidateGrounding` ZIELONY: KAZDA galaz STEP 0-5 — poprawny wybor ->
  Answered/grounding_status=validated; **`[v0.5]` id spoza `generation_context` (snapshot) -> RejectedUnknownUnit**
  (NIE wystarczy `∈ kandydaci`); zly hash z snapshotu -> RejectedHashMismatch; injection w body ->
  RejectedInjectionFilter (body NIEzmodyfikowany); pusty/zly JSON -> InvalidSchema (product_status NULL, bez tresci,
  bez retry); wyjatek -> InternalError; 0 accepted -> Abstained/LowConfidence.
- **`[v0.5]` STEP 1 macierz warunkowosci (F-04):** `answer`+pusty/duplikat ids -> InvalidSchema;
  `answer`+pole clarification/abstention -> InvalidSchema; `clarification`+puste pytanie/opcje -> InvalidSchema;
  `abstention_reason` niezgodny z `response_type` (np. OutOfScope@abstention) -> InvalidSchema; limity MAX_UNITS/MAX_OPTIONS/dlugosc.
- **`[v0.5]` Multi-unit atomowy (F-02):** `accepted.count() < parsed.answer_unit_ids.count()` -> `grounding_status=failed`
  -> Abstained (zaden render czesciowy).
- StatusDecisionTable: tabela decyzyjna (answerability x grounding/response_type) -> product_status +
  abstention_reason; reguly spojnosci (abstention_reason IFF Abstained; Abstained => 0 accepted;
  pola statusu NULL bez Completed).
- **`[v0.5]`** Multi-unit render wg `selected_ordinal` (kolejnosc z `answer_unit_ids[]`, nie `prompt_ordinal`);
  javascript/obcy host odrzucony; canonical_url tylko z manifestu; determinizm; pint czysty; brak hardkodu literalow statusu; grep krzakow == 0.

---

### Etap 7 — AskDocs Action: orkiestracja runtime + atomowy slad generacji
**Cel:** AskDocs jako jedyna orkiestracja runtime laczaca CandidateRetriever + AssistantClient +
ValidateGrounding i atomowo (jedna transakcja) utrwalajaca pelny slad: messages + generations +
generation_retrieval_candidates + generation_context + message_units + message_sources +
selected_generation_id. **`[v0.5]` Trzy poziomy idempotencji** (`operation_id` = logiczna operacja usera
[chroni przed double-click/refresh/parallel-tab], `request_id` = proba generacji, `provider_request_id` = OpenRouter),
ograniczony retry tylko przejsciowych awarii, fail-closed, limity wejscia app-side przed wywolaniem modelu (BudgetExceeded bez wywolania).
**Filary:** askdocs.
**`[FIX ordering 7->8]`** na koncu etapu **DTO/array-shape AskDocsResult ZAMROZONY** jako kontrakt
wejscia UI (warunek wejscia do Etapu 8).

**BRAMKA:**
- `php artisan test --compact --filter=AskDocs` ZIELONY: Answered + NeedsClarification + Abstained
  (NoMatchingUnit oraz LowConfidence po odrzuceniu wszystkich jednostek) + wszystkie InfraStatus.
- InvalidSchema NIE wyzwala retry i NIE produkuje tresci (product_status NULL, brak message_units/
  message_sources Accepted); ProviderRefusal/OutputTruncated/TransportInterrupted ROZLACZNE z
  InvalidSchema; retry WYLACZNIE dla ProviderTimeout/ProviderUnavailable/TransportInterrupted
  (>1 wiersz generations, **`[v0.5]` dokladnie jeden wskazany przez `messages.selected_generation_id` FK** —
  BOOL `selected_for_message` USUNIETY, FK egzekwuje „dokladnie jeden").
- **`[v0.5]` Idempotencja na poziomie OPERACJI:** double-submit tej samej `operation_id` (double-click/refresh/
  parallel-tab) NIE tworzy drugiej generacji ani drugiego kosztu (UNIQUE `operation_id`); `request_id` pozostaje
  per-proba (telemetria); transakcyjnosc: wyjatek -> pelny rollback, zero sierot.
- Dla Answered message_sources zawiera TYLKO Accepted, canonical_url z manifestu; accepted/rejected_units_count
  zgodne z message_units.validation_status; kill-switch AI degraduje bez 500.
- **DTO AskDocsResult zamrozony** (array-shape z PHPDoc) — udokumentowany jako kontrakt wejscia UI.

---

### Etap 8 — UI + Petla curation: Livewire 4 stany + Filament 5 review
**Cel:** dokonczyc prezentacje i domknac petle feedbacku. Livewire Chat renderuje WYNIK walidatora
(NIE samodeklaracji modelu) jako escaped plain text + linki z allow-listy: 4 rozlaczne UX (Answered
multi-unit/sources, NeedsClarification, Abstained z deep-linkiem wyszukiwarki VitePress, AWARIA
techniczna WIZUALNIE odrozniona od abstynencji), 👍/👎 + rating_reason_code, owner_token cookie
(surowy, HMAC liczy backend). Filament 5: Questions (rating=Down, grupowanie normalized_question_hash,
akcja „Utworz draft"), AnswerDrafts (edycja, DraftStatus, expired, BRAK akcji „serwuj odpowiedz"),
Generations (telemetria read-only). Petla: 👎 -> review -> edycja docs VitePress -> build-corpus;
answer_drafts to BRUDNOPIS (runtime NIGDY nie czyta, auto-wygasa).
**Filary:** ui, curation.

**BRAMKA:**
- `php artisan test --compact --filter=Chat` ZIELONY: render KAZDEGO z 4 stanow (Answered multi-unit+
  sources, NeedsClarification z opcjami, Abstained z deep-linkiem, Failure WIZUALNIE odrozniona) przy
  zamockowanym AskDocs; 👍 zapisuje rating=Up; 👎 wymusza rating_reason_code + zapisuje rating_comment
  przez Action; throttle blokuje po progu (takze sciezka awaryjna).
- Inspekcja: zaden link w UI nie pochodzi od modelu (canonical_url wylacznie z message_sources/manifest,
  host allow-list); render body = escaped plain text (brak `{!! !!}`); zero hardkodu stringow statusu/
  oceny w blade/Resource.
- Filament `/admin` wstaje (API F5): Questions filtruje rating=Down z grupowaniem normalized_question_hash
  + podglad message_sources; akcja „Utworz draft" tworzy answer_drafts z poprawnym corpus_version_seen;
  AnswerDrafts CRUD BEZ akcji serwowania; Generations read-only.
- `php artisan test --filter=CurationLoop`: runtime NIE czyta answer_drafts; ExpireStaleDraftsAction
  wygasza tylko corpus_version_seen<current AND status=Draft; DraftStatus::canTransitionTo dozwala
  Draft->Merged/Discarded, blokuje Merged->Draft; `grep -cP '[ÃÄÅ]'` nowe pliki == 0; pint czysty.

---

### Etap 9 — Bezpieczenstwo OS A: injection / PII / autoryzacja
**Cel:** **`[v0.5]` GLOWNA granica anti-injection = BUILD-TIME SECURITY GATE (Etap 3)** — jednostki
w kontekscie sa juz sklasyfikowane (`security_verdict==pass`; niejednoznaczne w kwarantannie), wiec chroniony
jest TAKZE proces decyzyjny modelu, nie tylko render. Warstwy RUNTIME ponizej = **defense-in-depth** (nie jedyna granica):
OutputInjectionFilter na renderowanym body (regex baseline, warstwa 2 maly model za flaga, NIGDY edycja -> RejectedInjectionFilter, 0 -> Abstained),
PreScreenUserInput (SecurityScreeningStatus = sygnal NIE granica), RedactPii (wzorce email/telefon,
NIE dowolne cyfry — numery bledow/ID/daty/wersje musza przejsc), throttle warstwowy per-IP
(CF-Connecting-IP) + globalny sufit, sekrety tylko `.env`, autoryzacja Filament (canAccessPanel +
reviewer policy). Red-team obu powierzchni jako klasy eval.
**Filary:** sec-injection.
**`[FIX ordering 9<->11]`** w tym etapie wymagane sa **KLASY eval** (na stubowanym adapterze, bez
sieci) + slot auto-uruchamiania przy deployu. Pelny pre-launch replay + kalibracja liczbowa odsetka
ODLOZONE explicite do Etapu 11 (nie sa twarda bramka Etapu 9).

**BRAMKA:**
- `php artisan test --filter=Security` ZIELONY: OutputInjectionFilterTest (injection ->
  RejectedInjectionFilter, tresc NIE edytowana), PreScreenUserInputTest (sygnal nie granica),
  RedactPiiTest (email/telefon redagowane; numer bledu/ID/data/wersja NIE — anty-za-szeroki-filtr,
  mierzony FP/FN), ChatThrottleTest (per-IP 429 + globalny sufit, throttle na sciezce awaryjnej),
  PanelAccessTest (canAccessPanel + reviewer policy).
- **`[FIX]`** Klasy red-team OBU powierzchni (prompt-injection user/docs, approved-doc-injection,
  prosba-o-system-prompt, zawiera-PII) ISTNIEJA i przechodza **na stubowanym adapterze**; pomiar
  ODSETKA na N uruchomieniach wzgledem progu = Etap 11.
- grep: zero hardkodu statusu (UnitValidationStatus/SecurityScreeningStatus z enuma); zero sekretow
  w kodzie; throttle aktywny na endpoincie (route:list/test); pint czysty; UTF-8 literal.

---

### Etap 10 — Bezpieczenstwo OS B: koszt / denial-of-wallet / odpornosc
**Cel:** warstwa ochrony finansowej: konserwatywny EstimateRequestCost pre-request, EnforceBudget
(cap max_input_tokens -> estymata>per-request -> budzet dzienny/miesieczny -> kill-switch -> circuit
breaker, kazde naruszenie konkretny InfraStatus BEZ wywolania AI), RecordGenerationCost (measured_cost,
finish_reason=length -> OutputTruncated), AiCircuitBreaker (Closed/Open/HalfOpen, lokalizacja uzgodniona
z userem), kill-switch AI (wylacza tylko AskDocs), idempotency request_id, `InfraStatus::isRetryable`
jako jedyne zrodlo prawdy o retry. Progi liczbowe = placeholdery do kalibracji pre-launch (DECYZJA #3).
**Filary:** sec-cost.
**`[FIX ordering 10<->11]`** kalibracja estimatora wzgledem `usage.prompt_tokens` ODLOZONA do Etapu 11;
tu testy deterministyczne struktury (bramka/breaker/retry-policy).
**`[FIX risk multi-instancja]`** ADR ustala: v1 = **single-instance** (udokumentowane zalozenie) LUB
wspolny store (Redis/DB) dla licznikow breakera/budzetu — jako warunek wejscia do prod (Etap 13).

**BRAMKA:**
- `php artisan test --filter=EnforceBudget` ZIELONY: estymata>budzet -> BudgetExceeded;
  input>max_input_tokens -> odrzut przed wyslaniem; breaker Open -> ProviderUnavailable bez wywolania AI.
- `php artisan test --filter=InfraStatusRetry` ZIELONY: `isRetryable()`==true WYLACZNIE dla
  ProviderTimeout/ProviderUnavailable/TransportInterrupted; false dla InvalidSchema/ProviderRefusal/
  OutputTruncated/RateLimited/BudgetExceeded.
- `php artisan test --filter=RecordGenerationCost` ZIELONY: finish_reason=='length' ->
  generations.infra_status==OutputTruncated; measured_cost zapisany z usage.cost; test kill-switch:
  AI=off -> sciezka awaryjna (InfraStatus!=Completed), zero wywolan providera, docs/frontend nietkniete.
- `chat:budget-report` zwraca agregaty; brak hardkodu progow (config/ai.php); brak literalow InfraStatus
  poza enumem; pint czysty; UTF-8 czysty.

---

### Etap 11 — Eval + jakosc + pre-launch replay (kalibracja progow)
**Cel:** wykonywalny eval-runner (taksonomia EvalCaseClass jako PHPUnit + dataset, EvalDimension
z rozdzialem deterministyczne-bramkowane vs mierzone-offline), PRE-LAUNCH REPLAY ~1000 syntetycznych
pytan z korpusu (kalibruje estimator vs usage.prompt_tokens, max_tokens vs output_tokens, progi breakera/
budzetu, mierzy abstention_rate per AbstentionReason, rozklad answerability_status, sygnal lost-in-the-middle),
auto-uruchamianie kluczowych klas jako bramka regresji. Jeden audytowalny zestaw liczb.
**DOMYKA** kalibracje placeholderow z Etapow 9-10 i bramke cache (Etap 1).
**Filary:** eval, sec-cost, sec-injection, retrieval, client.
**`[v0.5]` Replay syntetyczny z docs TO ZA MALO** (faworyzuje model generujacy dataset) — dochodza zbiory ROZLACZNE:
pytania PISANE PRZEZ LUDZI (bez podgladu nazw jednostek), realne/zanonimizowane, parafrazy+literowki, hard-negatives
(podobne procedury), multi-unit, nieaktualna-wersja-produktu, konfliktowe, injection w pytaniu I w jednostkach kontekstu,
holdout calych dokumentow. Metryki **PER-KLASA** (nie srednia globalna): candidate recall@K, selection-accuracy,
exact-set (multi-unit), completeness, abstention P/R, out-of-scope P, injection FP/FN, PII-redaction FP/FN — z **PRZEDZIALAMI UFNOSCI**.

**BRAMKA:**
- `php artisan test --compact tests/Feature/Eval tests/Unit/Eval` ZIELONE: kazda klasa EvalCaseClass
  (happy/failure/edge) na stubowanym adapterze BEZ klucza API; SchemaSmokeTest dowodzi plaskiej
  json_schema + ValidateGrounding STEP 0 InvalidSchema (fail-closed); rozlacznosc wymiarow
  (unit_integrity jedyny bramkowany w runtime, unit_relevance mierzony offline).
- `php artisan chat:eval-run --critical-only` exit 0 gdy klasy krytyczne (injection user/docs,
  approved-doc-injection, wybor-bez-pokrycia -> no_match, sprzeczne -> Conflicting, prosba-o-system-prompt,
  nieobecna -> NoMatchingUnit, poza-zakresem -> OutOfScope) osiagaja prog odsetka z config/eval.php;
  exit!=0 blokuje deploy.
- `php artisan chat:eval-replay --count=1000` zwraca audytowalny RAPORT: abstention_rate per
  AbstentionReason, rozklad answerability_status, margines estimator vs usage.prompt_tokens
  (estymata>=measured w >=X% przypadkow), udzial OutputTruncated < prog -> kalibracja max_tokens;
  raport ISTNIEJE PRZED ruchem publicznym (NO_GO bez niego).
- **`[FIX cache]`** bramka cache (Etap 1) domknieta: na realnym/paddingowym korpusie >=4096 tok
  TWARDO `cache_read_input_tokens>0` przy powtorzeniu.
- Progi placeholder z Etapow 9-10 (budzet/breaker/throttle/estimator margin/abstention_rate_max)
  WYPELNIONE wartosciami z kalibracji; klasy krytyczne uruchamiane N razy z raportem ODSETKA (nie pojedynczy pass/fail).
- **`[v0.5]` LICZBOWE progi wejscia na produkcje** (bramka prod, NIE „mechanizm gotowy"): selection-accuracy
  i completeness per-klasa z przedzialami ufnosci osiagaja zadeklarowane progi na zbiorach ludzkich/adwersaryjnych
  (answer-units = selektor, nie generator trafnosci — kontrakt A v0.5). Brak progow = NO_GO publiczny.

---

### Etap 12 — Retencja / RODO / prywatnosc (twardy delete + rotacja peppera)
**Cel:** polityka retencji jako RELACJE (korpus >= logi >= messages; dni = config placeholder DO
KALIBRACJI, INVARIANT jako boot-time asercja), atomowy PurgeConversationAction (strefa CASCADE 1),
ForgetOwnerAction (RODO erasure po owner_token_hash dla WSZYSTKICH key_version), HashOwnerTokenAction/
VerifyOwnerTokenAction (HMAC-SHA-256 wersjonowanym pepperem; weryfikacja uzywa wersji z wiersza NIE
current), GarbageCollectCorpusVersionsAction (GC tylko gdy zaden zywy log nie referuje), rozstrzygniecie
z ADR (telemetria kosztow przezywa RODO przez rollup do nie-PII). Komendy chat:purge-expired + chat:rotate-pepper.
**Filary:** retention.
**`[FIX content_snapshot]`** GC korpusu realizuje TE SAMA opcje retencji co filar deploy (z ADR Etapu 0,
rekomendacja opcja A: guard `NOT EXISTS`, brak snapshotu body).

**BRAMKA:**
- `php artisan test --filter=Privacy` ZIELONY: PurgeConversationTest dowodzi ZERO sierot we wszystkich
  tabelach strefy 1 (assert count==0 per RetentionTarget); ForgetOwnerTest: po erasure dla owner_token_hash
  w 2 wersjach peppera — 0 rozmow ownera, telemetria skasowana, rollup kosztu zachowany i NIE zawiera owner_token.
- OwnerTokenPepperRotationTest: po chat:rotate-pepper stara rozmowa weryfikowalna (pepper po key_version),
  nowa niesie nowy key_version; ForgetOwner iteruje po WSZYSTKICH wersjach (pelne zapomnienie sprzed rotacji).
- RetentionRelationInvariantTest: config korpus<logi rzuca wyjatek przy boot (INVARIANT korpus>=logi>=messages);
  GarbageCollectCorpusVersions NIE kasuje corpus_version z aktywnym odwolaniem (guard NOT EXISTS), kasuje
  osierocona; atomowosc: wymuszony wyjatek w transakcji -> rollback.
- grep zero literalow dni i nazw tabel w app/Actions/Privacy (config + RetentionTarget enum); twardy delete; pint czysty.

---

### Etap 13 — Deploy / operacje + 10 warunkow wejscia do produkcji
**Cel:** atomowy lancuch wdrozeniowy fail-closed: `deploy.sh` (git reset -> composer -> npm build ->
migrate --force -> chat:build-corpus --publish [immutable, bez swap] -> chat:eval --gate [regresja
kluczowych klas BLOKUJE swap] -> swap current_corpus_version -> config:cache -> reload), cron
chat:purge-expired (retencja korpus>=logi, 3 strefy cascade), vhost nginx+PHP-FPM za Cloudflare,
`.env` raz na serwerze (OPENROUTER_API_KEY NIE w gicie), DEPLOY.md (model `openai/gpt-5.4-nano`,
fallback `mistralai/ministral-14b-2512`). Zwolnienie do prod zaleznie od warunkow wejscia (sek. 13 v0.5).
**Filary:** deploy, eval, corpus, retention, sec-injection, sec-cost.
**`[FIX multi-instancja]`** warunek wejscia do prod: „tryb instancji potwierdzony" (single-instance
udokumentowany LUB wspolny store breakera/budzetu + wspolny magazyn korpusu).

**BRAMKA:**
- `chat:build-corpus --publish --dry-run` deterministyczny (powtorny run -> identyczny manifest_hash);
  `--publish` tworzy artefakt pod nowa corpus_version NIE nadpisujac poprzedniej; po swap current
  wskazuje nowa; rollback = przestawienie wskaznika BEZ rebuildu (test feature swap N->N-1).
- BRAMA FAIL-CLOSED: deploy.sh z wymuszona regresja kluczowej klasy eval -> `chat:eval --gate` exit!=0
  -> swap NIE nastepuje, deploy konczy bledem, current_corpus_version NIEZMIENIONY (test: stub eval exit 1);
  trigger answer_drafts.expired po publish; PurgeRetention NIE usuwa corpus_version referowanej przez zywy log.
- `php artisan schedule:list` pokazuje chat:purge daily; SMOKE: `curl -I /` => 200/30x, `curl -I /admin`
  => 302; grep sekretu w kodzie/skryptach == 0 (tylko .env); `grep -cP '[ÃÄÅ]'` DEPLOY.md/deploy.sh/config
  == 0; pint czysty.
- **WARUNKI WEJSCIA sek.13 v0.5** spelnione i udokumentowane: build-time security gate + klasyfikator wyjscia
  anti-injection (#4), red-team obu powierzchni (#8), polityka redakcji PII wzorce-nie-cyfry z FP/FN (#11),
  provider config data_collection:deny/zdr:true (#12), pre-launch replay raport ISTNIEJE i progi skalibrowane (#13),
  **tryb instancji potwierdzony**; pelny `php artisan test --compact` (oba testsuite) GREEN.
- **`[v0.5]` Dodatkowe warunki prod:** (a) LICZBOWE progi selekcji (selection-accuracy/completeness per-klasa
  z przedzialami ufnosci) osiagniete — nie „mechanizm gotowy"; (b) spec `AI_ASSISTANT_DESIGN.md` v0.5 ZACOMMITOWANY
  + otagowany (reprodukowalnosc audytu — dotad untracked working-tree); (c) determinizm endpointu OpenRouter
  ROZSTRZYGNIETY (jeden endpoint reprodukowalny vs canary per dopuszczony endpoint); data_collection/zdr = filtry
  routingu, NIE zastepuja oceny umownej/DPA dostawcy.

---

## 4. FILARY (szczegol)

> Per filar: cel, deliverables, kroki (skrot), bramka, ryzyka, odlozone, odniesienia A-I.
> Pelne kontrakty i ryzyka w briefach filarow; tu wersja nawigacyjna.

### 4.1 `db` — Schemat DB + enumy + modele
**Cel:** fundament danych: ~11 tabel MySQL 8.4, enumy PHP-backed (jedyne zrodlo prawdy), modele Eloquent
— w zgodzie z kontraktem A-I (sek. 2a/8/9) i konwergencja. Zamrazany DOPIERO po prototypie pionowym.
**`[FIX]` JEDYNY wlasciciel WSZYSTKICH migracji i modeli** (w tym answer_drafts, corpus_versions,
answer_unit_versions). Pozostale filary konsumuja.

**Deliverables (skrot):** enumy kanonu sek.8 (Role, ProductStatus[BEZ AnsweredPartial], AbstentionReason,
AnswerabilityStatus, UnitValidationStatus, InfraStatus[BEZ GroundingFailed], Rating, ReasonCode)
+ GroundingStatus wg rozstrzygniecia ADR; metody `label()`/`color()`/`icon()`/`options()`;
migracje ~11 tabel (kolejnosc wg FK); modele z `$fillable` + `casts()` + relacje; `Str::ulid()`
w `creating()` tylko Conversation; factory per model; test SchemaFoundation.
**`[v0.5]`** + enum `ModelResponseType` (rozdzial od `answerability_status`); pola: `operation_id`/`request_id`/
`provider_request_id`, `selected_ordinal`, `message_units.content_hash`/`document_id` NULLABLE,
`raw_question_encrypted`+`redacted_question`, owner_token format `v<ver>.<token>`, `security_verdict`/`classified_content_hash` na answer_unit_versions.

**Kroki:** 1) GATE projektowy (GO na enumy+tabele) 2) enumy 3) migracje (kolejnosc FK) 4) CHECK
jednokierunkowe + UNIQUE + INDEX (kontrakt E) 5) modele 6) factory + test 7) baseline-kandydat PO prototypie.

**Bramka:** `migrate:fresh` na mysql; `--filter=SchemaFoundation` zielony; grep zero hardkodu;
`grep -cP '[ÃÄÅ]' app/Enums/*.php`==0; pint; modele z `$fillable` (nie `$guarded=[]`); ULID tylko Conversation.

**Ryzyka:** przedwczesne zamrozenie (mityg.: prototyp pionowy); rozjazd enum<->VARCHAR (test dlugosci);
CHECK MariaDB vs MySQL (trzymac minimalne+jednokierunkowe); 4 enumy „do ustalenia" (zapytac w GATE).

**Odlozone:** tenant_id/user_id (DECYZJA #1 PUBLICZNY, addytywnie); klastrowanie semantyczne; okna retencji;
split messages user/assistant; approved_answers (USUNIETE z modelu).

**Odniesienia:** kontrakt A/C/D/E/F; konwergencja (selected_generation_id FK, 3 strefy cascade, CHECK
jednokierunkowe, public_id ULID tylko conversations, enumy PHP-backed+VARCHAR).

---

### 4.2 `corpus` — Korpus pipeline (`chat:build-corpus`)
**Cel:** deterministyczny, fail-closed pipeline: ekstrakcja VitePress (pinned ref) -> atomowe answer-units
+ indeks chunkow + manifest, 5-warunkowy gate, sync do WERSJONOWANEGO IMMUTABLE registry
(corpus_versions + answer_unit_versions, NIE TRUNCATE), atomowy swap + trigger answer_drafts.expired.

**Deliverables (skrot):** BuildCorpusCommand (cienka); Actions: ExtractAnswerUnits, BuildCandidateChunks,
ApplyCorpusGate, BuildManifest, ValidateCorpus, PublishCorpusVersion; **enum CorpusStatus (WLASCICIEL `[FIX-f]`)**
+ UnitGateExclusionReason; modele CorpusVersion/AnswerUnitVersion; config/docs.php; testy gate/walidacja/idempotencja/swap.

**Kroki:** 1) config + enumy 2) migracje registry (temp-DB) 3) ExtractAnswerUnits (answer_unit_id stabilny
!= content_hash) 4) chunking 3-stopniowy 5) gate fail-closed 6) manifest (canonical_url) 7) walidacja
(build PADA) 8) publish immutable + atomowy swap + trigger 9) komenda spina 10) testy.

**Bramka:** build na fixtures exit 0 + corpus_version + manifest_hash; gate per-warunek; build PADA per-warunek;
idempotencja (ten sam ref -> ten sam manifest_hash); stabilnosc id; atomowy swap; trigger expired;
zakaz wycieku (nie w public/); pint; UTF-8.

**Ryzyka:** frontmatter kings5-docs moze nie istniec (gate wyklucza wszystko — fixtures + uzgodnienie);
krucha ekstrakcja VitePress (test portal<->korpus); brak exact tokenizera (estimator); stabilnosc id
przy reorg docs; granularnosc answer-unit nieustalona; wspolny magazyn vs lokalny.

**Odlozone:** retrieval etap 1/2; lematyzacja PL; pola in-panel; multi-unit policy; GC corpus_version;
cron/scheduler build; pelny pre-launch replay; magazyn S3-like.

**Odniesienia:** kontrakt A/F/E/G/I; registry wersjonowany immutable; 3 strefy cascade (RESTRICT korpus).

---

### 4.3 `retrieval` — Retrieval
**Cel:** szew CandidateRetriever (dobor kandydatow != grounding); etap 0 FullCorpusRetriever (caly aktywny
corpus_version po GATE F); konserwatywny TokenEstimator + budzetowanie kontekstu; szkielet etapu 1
(LexicalRetriever, lematyzacja PL TYLKO w retrievalu).

**Deliverables (skrot):** interfejs CandidateRetriever (`app/Actions/Retrieval/` — `[FIX]` NIE nowy
top-level `app/Contracts`; uzgodnic z userem); RetrievedAnswerUnit (readonly VO/array-shape, bez DTO-lib);
FullCorpusRetriever; LexicalRetriever (szkielet); TokenEstimator; BudgetCandidates; enum ContextStrategy
{FullCorpus,Lexical,Vector}; config retrievalu; binding; testy.

**Kroki:** 1) uzgodnic lokalizacje interfejsu 2) ContextStrategy + config 3) RetrievedAnswerUnit
4) interfejs + FullCorpusRetriever 5) TokenEstimator + budzetowanie 6) binding (feature flag) 7) testy
8) szkielet LexicalRetriever (odlozona implementacja do progu corpus_tokens).

**Bramka:** `--filter=FullCorpusRetriever` zielony (po GATE F, rank/score=null); **`[FIX]` `--filter=TokenEstimator`
deterministyczny na probkach ze znanym token_count (bez sieci)**; szew (grep FullCorpusRetriever poza
binding/testy==0); budzet (suma<=max_input_tokens; przekroczenie loguje corpus_tokens).

**Ryzyka:** lost-in-the-middle (etap 0 caly korpus -> proaktywny etap 1 wg progu); brak exact tokenizera;
cache pada od etapu 1; lematyzacja PL; granularnosc answer-unit.

**Odlozone:** etap 2 wektory (Qdrant); pelna implementacja LexicalRetriever; prompt caching kandydatow;
metryki recall@k/MRR (od etapu 1); in-panel authz; kalibracja progow.

**Odniesienia:** kontrakt A/D/F/G/E/I; ContextStrategy; registry immutable.

---

### 4.4 `client` — Klient AI / OpenRouter
**Cel:** cienka warstwa transportowo-kontraktowa nad OpenRouter; plaski strict json_schema (kontrakt B);
surowy AssistantResult do walidatora (BEZ parsowania kontraktu/naprawy JSON/auto-retry); 10 rozlacznych
InfraStatus; provider pinning; AssistantCapabilityProfile (eval-gate przy zmianie modelu).

**Deliverables (skrot):** enumy InfraStatus (`[FIX-f]` wspoldzielony plik), ModelResponseType, AbstentionReason;
config/openrouter.php; BuildResponseSchema; AssembleAssistantPrompt; AssistantClient; AssistantResult;
AssistantCapabilityProfile; komenda `chat:assistant-smoke` (TWARDA BRAMKA); testy AssistantClient/ResponseSchema.

**Kroki:** 1) enumy 2) config pinning 3) BuildResponseSchema (plaski) 4) AssemblePrompt (SYSTEM/USER zaufanie)
5) AssistantClient (mapowanie InfraStatus) 6) CapabilityProfile 7) smoke-test 8) testy Http::fake() 9) throttle/kontrakt kosztowy.

**Bramka:** `chat:assistant-smoke` exit 0; cache `[FIX]` przeniesiony do osobnej bramki >=4096 tok (Etap 11);
`--filter=AssistantClientTest`/`ResponseSchemaTest` zielone; asercja provider pinning w body; grep sekretu==0; pint.

**Ryzyka:** KRYTYCZNE — OpenRouter normalizuje refusal/finish_reason (mapowanie w adapterze, weryfikacja
empiryczna); strict zalezny od endpointu (provider.only zawezic); brak exact tokenizera; cache prefiks
bajt-stabilny; slugi providerow niepotwierdzone; ZDR moze zawezic pule endpointow.

**Odlozone:** eval-runner (osobny filar, ale szkielet RAZEM `[FIX]`); walidator backendu; model danych;
realny retriever; Filament telemetria; kalibracja max_tokens; anyOf-z-const; throttle/breaker (filar sec).

**Odniesienia:** kontrakt A/B/C/D/G/H/I; zasada provider-agnostic (CapabilityProfile).

---

### 4.5 `validator` — Walidator groundingu (ValidateGrounding)
**Cel:** deterministyczny Action STEP 0-5, rdzen anty-halucynacyjny. Rozlaczne osie statusu, werdykty
per jednostka, render escaped plain-text z linkami z manifestu. Model nigdy nie jest sedzia wlasnego groundingu.

**Deliverables (skrot):** enumy (InfraStatus, AnswerabilityStatus, GroundingStatus, ProductStatus,
AbstentionReason, UnitValidationStatus, ModelResponseType); ValidateGrounding (execute(GroundingInput)
-> GroundingResult); VO GroundingInput/Result/CandidateUnit/UnitVerdict; OutputInjectionFilter (regex baseline);
renderer multi-unit; config (allow-lista hosta, wzorce klasyfikatora); test pelnej tabeli decyzyjnej.

**Kroki:** 1) enumy 2) VO 3) STEP 0 strict parse + fail-closed 4) STEP 1 warunkowosc 5) STEP 2 walidacja
jednostek 6) STEP 3 klasyfikator + grounding_status 7) STEP 4 answerability 8) STEP 5 product_status + render
9) testy 10) pint.

**Bramka:** `--filter=ValidateGrounding` zielony (kazda galaz, kazdy UnitValidationStatus, kazdy fail-closed);
**`[v0.5]`** walidacja `∈ generation_context` (nie kandydaci) + pelna macierz STEP 1 (4 warianty) + multi-unit
ATOMOWY (czesciowa akceptacja -> failed); pusty answer_unit_ids -> InvalidSchema; injection -> RejectedInjectionFilter
(body niezmieniony); multi-unit render wg `selected_ordinal`; obcy host odrzucony; determinizm; pint; brak hardkodu.

**Ryzyka:** `[FIX]` fail-mode klasyfikatora ML jawny (degradacja regex-only w v1); ProviderRefusal rozlaczny
(weryfikacja claude-api); regex FP/FN; strict-JSON PHP vs Anthropic (smoke trasy); content_hash identyczny z buildem.

**Odlozone:** maly model w STEP 3 (regex-only v1); prog pewnosci; relevance/entailment bramkowanie (eval);
AnsweredPartial (usuniete); markdown allow-list; auto-retry (AskDocs/client); in-panel authz.

**Odniesienia:** kontrakt A/B/C/D/E; algorytm STEP 0-5 + tabela decyzyjna; konwergencja (message_units bez evidence).

---

### 4.6 `askdocs` — AskDocs Action (orkiestracja)
**Cel:** jedyna orkiestracja runtime: CandidateRetriever + AssistantClient + ValidateGrounding +
atomowy zapis sladu generacji. Idempotency, ograniczony retry (tylko przejsciowe), fail-closed.
**`[FIX]` NIE wlasciciel migracji** — konsumuje modele filaru db.

**Deliverables (skrot):** AskDocs Action; AskDocsRequest/Result (array-shape); modyfikacja Chat::sendMessage;
transakcyjny zapis (messages/generations/candidates/context/units/sources); logika retry/InfraStatus;
test feature (happy + InfraStatus + idempotencja + transakcyjnosc).

**Kroki:** 1) zatwierdzic kontrakty interfejsow 2) enumy (import) 3) AskDocsRequest + normalizacja
4) zapis user + szkielet assistant 5) retriever + prompt 6) AssistantClient + InfraStatus 7) retry przejsciowy
8) walidator 9) atomowy zapis sladu 10) fakt produktowy + selected_generation 11) Result + Livewire 12) testy.

**Bramka:** `--filter=AskDocs` zielony; InvalidSchema bez retry/tresci; retry tylko przejsciowy
(**`[v0.5]`** jeden wskazany przez `messages.selected_generation_id` FK — BOOL usuniety); **`[v0.5]`** idempotencja
`operation_id` (logiczna operacja: double-click/refresh nie tworzy 2. kosztu), `request_id` per-proba;
transakcyjnosc (rollback, zero sierot); message_sources tylko Accepted z manifestu; kill-switch bez 500; **DTO Result zamrozony `[FIX 7->8]`**.

**Ryzyka:** sprzezenie z 3 filarami (krok 1 zamraza interfejsy); wlasnosc migracji (`[FIX]` db=wlasciciel);
reguly spojnosci w Action; request_id<->retry semantyka; InfraStatus mapowanie; denial-of-wallet hooki;
lost-in-the-middle (candidates vs context).

**Odlozone:** auto-retry/healing InvalidSchema; few-shot approved_answers; klastrowanie pytan; generative+grader;
kompaktowanie historii; progi denial-of-wallet; okna retencji; in-panel; anyOf-z-const.

**Odniesienia:** kontrakt A-I; konwergencja (selected_generation_id FK, 3 strefy cascade, CHECK jednokierunkowe);
zasada sekwencji.

---

### 4.7 `ui` — UI (Livewire chat + Filament curation)
**Cel:** prezentacja WYNIKU walidatora (nie samodeklaracji modelu); 4 rozlaczne UX; 👍/👎 + reason_code;
owner_token cookie; Filament Questions/AnswerDrafts/Generations.

**Deliverables (skrot):** Chat.php (DI AskDocs, mapowanie, owner_token, rate()); chat.blade + partiale
(_message, _abstention NOWY, _failure NOWY, _clarification NOWY); Filament Resources Questions/AnswerDrafts/
Generations (F5 API); konsumpcja enumow przez label/color/options.

**Kroki:** 1) mapowanie DTO (papierowo) 2) _message multi-unit + sources 3) partiale clarification/abstention/
failure 4) Chat: DI + owner_token + rating 5) Questions 6) AnswerDrafts 7) Generations read-only 8) testy + UTF-8.

**Bramka:** `--filter=Chat` zielony (4 stany); 👍/👎 przez Action; throttle; zaden link od modelu
(message_sources/manifest, allow-list); escaped plain text; Filament wstaje (F5); zero hardkodu w blade/Resource; UTF-8; pint.

**Ryzyka:** zlanie abstynencji z awaria (osobne partiale + test); DTO niezamrozony (`[FIX]` zamrozony na koncu
Etapu 7); render injection (escaped `{{ }}`, nie `{!! !!}`); link spoza allow-listy; Filament 5 API z pamieci
(weryfikowac vendor/); owner_token hashowanie (backend liczy HMAC); synchroniczne wywolanie (wire:loading).

**Odlozone:** historia rozmow (v2); streaming; klastrowanie pytan; bogaty edytor draftow; dashboard telemetrii;
few-shot UX; klikalne sugestie (od etapu 1 retrievalu).

**Odniesienia:** kontrakt A/C/D/E/F; tabela decyzyjna sek.6; sek.9 curation; konwergencja (public_id ULID,
rating w messages).

---

### 4.8 `curation` — Petla curation
**Cel:** 👎 -> review Filament -> admin edytuje docs VitePress (JEDYNE zrodlo prawdy) -> commit ->
chat:build-corpus. answer_drafts = WYLACZNIE brudnopis (runtime NIGDY nie czyta; auto-wygasa; Merged =
„wcommitowano do repo", nie „serwujemy z bazy"). Zero approved_answers produkcyjnych.

**Deliverables (skrot):** enumy DraftStatus {Draft,Merged,Discarded} (canTransitionTo), Rating, ReasonCode;
model AnswerDraft (`[FIX]` migracja u filaru db); Actions CreateAnswerDraft/TransitionAnswerDraft/
ExpireStaleDrafts; Filament QuestionResource/AnswerDraftResource/GenerationResource (opcjonalny); factory + test.

**Kroki:** 1) enumy 2) (migracja u db) model AnswerDraft 3) Actions 4) wpiecie ExpireStaleDrafts w build-corpus
5) Questions (rating=Down) 6) AnswerDrafts (BRAK serwowania) 7) Generations read-only 8) factory + testy 9) pint+UTF-8.

**Bramka:** migracja answer_drafts (u db) bez bledu; `--filter=CurationLoop` (runtime NIE czyta answer_drafts;
ExpireStaleDrafts wygasza tylko corpus_version_seen<current AND Draft); canTransitionTo (Draft->Merged/Discarded,
blokuje Merged->Draft); zero hardkodu; Filament wstaje (BRAK „serwuj odpowiedz"); po build expired=true; pint+UTF-8.

**Ryzyka:** drugie zrodlo prawdy tylnymi drzwiami (BRAK akcji serwowania + test); zaleznosc od messages/
corpus_version (prototyp pionowy); Filament 5 API z pamieci; trigger wygaszania atomowo ze swap; ReasonCode
do uzgodnienia z owner; MySQL ENUM zamiast VARCHAR (cast).

**Odlozone:** approved_answers produkcyjne (USUNIETE); few-shot z draftu; klastrowanie pytan; auto draft->PR;
GenerationResource; CI-gate eval na commit.

**Odniesienia:** kontrakt A/C/D/E/F; sek.9 (P0.5, trzy gwarancje); konwergencja (draft=SET NULL).

---

### 4.9 `eval` — Eval + jakosc
**Cel:** wykonywalny eval-runner RAZEM z adapterem (kontrakt I, NIE odlozony); taksonomia klas (PHPUnit +
dataset); PRE-LAUNCH REPLAY (kalibracja estimatora/progow, abstention_rate) — **`[v0.5]`** replay syntetyczny
z docs to ZA MALO, dochodza zbiory ludzkie/adwersaryjne (parafrazy, hard-negatives, multi-unit, konfliktowe,
injection, holdout) + metryki PER-KLASA (selection-accuracy/completeness) z przedzialami ufnosci = LICZBOWE progi prod;
metryki rozlaczne (unit_integrity bramkowane vs unit_relevance offline); auto-uruchamianie klas krytycznych przy deployu.

**Deliverables (skrot):** enumy EvalCaseClass (taksonomia P1.3, isCritical()) + EvalDimension (isGatedInRuntime
tylko UnitIntegrity); dataset eval_cases.jsonl; Actions RunEvalSuite/RunPreLaunchReplay/CalibrateTokenEstimator;
komendy chat:eval-run / chat:eval-replay; testy taksonomii/schema-smoke/enumow; config/eval.php (progi placeholdery).

**Kroki:** 1) enumy 2) dataset 3) testy na stubie NAJPIERW (bez sieci) 4) RunEvalSuite (orkiestruje istniejace Action)
5) komendy + deploy 6) pre-launch replay + kalibracja 7) jeden audytowalny zestaw liczb.

**Bramka:** `tests/Feature/Eval tests/Unit/Eval` zielone (stub, bez klucza); `chat:eval-run --critical-only`
exit 0 wg progu (blokuje deploy); `chat:eval-replay --count=1000` raport (abstention_rate/answerability/margines
estimatora) ISTNIEJE przed ruchem; rozlacznosc wymiarow (unit_integrity jedyny bramkowany).

**Ryzyka:** zaleznosc rownolegla (interfejsy przed implementacja; stub odblokowuje); niedeterminizm (N razy,
odsetek); zatruta telemetria abstention (lost-in-the-middle); brak exact tokenizera; koszt replayu (offline,
parametryzowalny); over-fit datasetu (zachowanie, nie tresc).

**Odlozone:** recall@k/MRR (od etapu 1); generative+grader; prog pokrycia na klase; klasy cross-tenant
(in-panel); progi liczbowe (kalibracja); ciagly monitoring realnego ruchu; redakcja PII (osobny zestaw).

**Odniesienia:** kontrakt I/A/C/B/D/G; P1.2/P1.3 (rozlaczne wymiary; 👍/👎 sygnal pomocniczy).

---

### 4.10 `sec-injection` — Bezpieczenstwo OS A: injection / PII / autoryzacja
**Cel:** **`[v0.5]` glowna granica anti-injection = build-time security gate (Etap 3, filar corpus)**; warstwy
ponizej = defense-in-depth. Obrona OBU powierzchni injection (input + approved-body verbatim); redakcja PII jako
osobna polityka; throttle warstwowy; sekrety .env; autoryzacja Filament; red-team jako klasy eval.

**Deliverables (skrot):** enumy UnitValidationStatus, SecurityScreeningStatus {Clean,Flagged}; Actions
OutputInjectionFilter, PreScreenUserInput, RedactPii; config/security.php; RateLimiter (bootstrap/app.php
+ provider); User implements FilamentUser + canAccessPanel; testy (injection/prescreen/PII/throttle/panel);
klasy eval red-team.

**Kroki:** 1) enumy 2) config (progi=placeholdery) 3) OutputInjectionFilter (STEP 3, nigdy edycja)
4) PreScreen + RedactPii 5) throttle per-IP+global (CF) 6) autoryzacja Filament 7) testy 8) eval red-team (N razy).

**Bramka:** `--filter=Security` zielony; eval red-team OBU powierzchni N razy odsetek>=prog (`[FIX]` na
stubie w Etapie 9; pomiar odsetka w Etapie 11); klasa zawiera-PII brak echa; zero hardkodu/sekretow; throttle
aktywny; klasy auto przy deployu (regresja blokuje); pint; UTF-8.

**Ryzyka:** klasyfikator = sygnal nie gwarancja (granica = kto zatwierdza do approved); `[FIX]` maly model
fail-mode jawny; PII best-effort (FP/FN); throttle per-IP obejscie (global+kill-switch+breaker); niedeterminizm
(odsetek); CF-Connecting-IP (trusted proxies); pepper rotacja.

**Odlozone:** detektor ML injection; cross-tenant gating (in-panel); per-anon-token throttle (v2); CAPTCHA;
progi liczbowe; CI-gate na commit; rotacja peppera (operacyjne); twarda anonimizacja PII.

**Odniesienia:** kontrakt C/E/G/I; sek.7 P0.2/P0.3; warunki wejscia #4/#8/#11/#12.

---

### 4.11 `sec-cost` — Bezpieczenstwo OS B: koszt / denial-of-wallet / odpornosc
**Cel:** ochrona finansowa platnego endpointu: estimator pre-request, EnforceBudget, RecordGenerationCost,
circuit breaker, kill-switch, idempotency, retry tylko przejsciowy. Progi = placeholdery do kalibracji pre-launch.
**`[FIX-f]`** wlasciciel semantyki retry/koszt InfraStatus (isRetryable/isTransient).

**Deliverables (skrot):** enum InfraStatus (isTransient/isRetryable); config/ai.php (budget/estimator/breaker/
pricing); Actions EstimateRequestCost/EnforceBudget/RecordGenerationCost/ToggleAiKillSwitch; AiCircuitBreaker
(`[FIX]` app/Services/ wymaga GO — alt. Action na Cache); komenda chat:budget-report; RateLimiter; testy;
pre-launch replay hook.

**Kroki:** 1) InfraStatus (retry/transient) 2) config 3) estimator (czysty) 4) EnforceBudget (kolejnosc bramek)
5) breaker + kill-switch 6) RecordGenerationCost 7) idempotency + throttle 8) raport 9) testy + replay hook.

**Bramka:** `--filter=EnforceBudget`/`InfraStatusRetry`/`RecordGenerationCost` zielone; kill-switch (AI off ->
sciezka awaryjna, docs nietkniete); `[FIX]` kalibracja estimatora vs usage.prompt_tokens -> Etap 11;
budget-report agregaty; brak hardkodu progow; pint; UTF-8.

**Ryzyka:** DECYZJA #3 nierozstrzygnieta (placeholdery; bramka blokuje prod); brak exact tokenizera; OutputTruncated
!= awaria (osobny status); breaker w app/Services (GO usera); `[FIX]` wspoldzielony stan przy >1 instancji
(warunek prod: single-instance LUB wspolny store); throttle za CF; granica OS A/OS B (wspolny enum, rozdzial semantyki).

**Odlozone:** kalibracja numeryczna (DECYZJA #3 + replay); CAPTCHA; cache TTL 1h; per-anon-token (v2);
max_price safety-net; wspoldzielony store (multi-instancja); alerting zewnetrzny.

**Odniesienia:** kontrakt G/C/D/E/I; P0.6; DECYZJA #3; konwencje §1/§2/§4/§7.

---

### 4.12 `retention` — Retencja / RODO / prywatnosc
**Cel:** retencja jako RELACJE (korpus>=logi>=messages; dni=config); atomowy purge; RODO erasure po
owner_token_hash; rotacja peppera bez osierocania; GC korpusu; rozstrzygniecie telemetrii kosztow (rollup).
**`[FIX content_snapshot]`** TA SAMA opcja retencji co filar deploy (ADR Etapu 0).

**Deliverables (skrot):** enum RetentionTarget; config/retention.php (INVARIANT boot-time) + config/owner_token.php
(wersjonowana mapa pepperow); Actions HashOwnerToken/VerifyOwnerToken/PurgeConversation/ForgetOwner/
GarbageCollectCorpusVersions; komendy chat:purge-expired/chat:rotate-pepper; migracja CASCADE strefy 1 (u db);
testy; DECYZJA-NOTA w DATABASE_SCHEMA.md.

**Kroki:** 1) inwentaryzacja stref cascade + owner_token 2) enum + config (INVARIANT) 3) hash/verify wersjonowane
4) atomowy purge + RODO erasure (wszystkie key_version) 5) GC korpusu (guard NOT EXISTS) 6) komendy 7) decyzja
telemetria vs RODO (rollup) 8) prototyp + testy, potem zamrozenie.

**Bramka:** `--filter=Privacy` zielony (zero sierot per RetentionTarget; ForgetOwner 2 wersje peppera; rollup bez
owner_token); rotacja (verify po key_version; ForgetOwner wszystkie wersje); INVARIANT (boot exception); GC guard
NOT EXISTS; atomowosc (rollback); zero literalow dni/tabel w Actions; twardy delete; pint.

**Ryzyka:** linkability po anonimizacji (rekomendacja rollup); cascade vs RODO (korpus RESTRICT); surowy token
przezywa rotacje (verify z wiersza); ForgetOwner tylko current = niepelne (wszystkie wersje); dni przedwczesnie
(config); GC zbyt agresywny (audytowalnosc); atomowosc masowego purge (batch ShouldQueue).

**Odlozone:** pelna polityka PII (osobna); soft-delete; tenant_id/user_id RODO (in-panel); eksport danych;
scheduler cron; liczby dni/prog GC (kalibracja); KMS/HSM dla peppera.

**Odniesienia:** kontrakt E/A/F; sek.7 (prywatnosc); sek.8 (retencja); decyzje spec #5/#6.

---

### 4.13 `deploy` — Deploy / operacje
**Cel:** atomowy lancuch wdrozeniowy fail-closed; build immutable + atomowy swap; cron purge (korpus>=logi);
vhost nginx+PHP-FPM za Cloudflare; .env raz; DEPLOY.md (OPENROUTER + model id).

**Deliverables (skrot):** deploy.sh (idempotentny, set -euo pipefail); BuildCorpusCommand/BuildCorpus/
PublishCorpusVersion (atomowy swap + trigger); PurgeExpiredCommand/PurgeRetention (korpus>=logi); routes/console.php
(schedule purge); config/docs.php (artifact_path abstrakcja); DEPLOY.md; cron entry.

**Kroki:** 1) prototyp pionowy temp-DB 2) config + .env (OPENROUTER) 3) BuildCorpus + komenda 4) PublishCorpusVersion
(immutable + atomowy swap) 5) chat:eval --gate w deploy.sh (fail-closed) 6) kolejnosc deploy.sh (build+eval+swap PRZED
config:cache) 7) PurgeRetention + harmonogram 8) vhost nginx+FPM+CF 9) DEPLOY.md + pint.

**Bramka:** `--publish --dry-run` deterministyczny (identyczny manifest_hash); publish nie nadpisuje poprzedniej;
rollback = wskaznik bez rebuildu; BRAMA FAIL-CLOSED (eval regresja -> brak swap); trigger expired; PurgeRetention nie
usuwa referowanej corpus_version; schedule:list; SMOKE (curl / 200, /admin 302); grep sekretu==0; UTF-8; pint;
`[FIX]` tryb instancji potwierdzony.

**Ryzyka:** wspolny magazyn vs storage/ (abstrakcja config); atomowosc swap (transakcja/rename); eval --gate koszt/
flaky (replay offline); retencja korpus>=logi (snapshot alt.); --ignore-platform-req=php (zawezic); build wydluza okno
(build przed swap).

**Odlozone:** magazyn blue/green (S3-like); cron rebuild (webhook); retrieval etap 1; prompt caching (zmierzony ruch);
snapshot jednostki; health-check artefaktu; blue/green zero-downtime.

**Odniesienia:** kontrakt A/E/F/G/I; P1.10; registry immutable; 3 strefy cascade; zasada sekwencji.

---

### 4.14 `tests` — Testy (PHPUnit)
**Cel:** wykonywalna siatka bezpieczenstwa dowodzaca fail-closed walidatora, integralnosci migracji (3 strefy
cascade, registry immutable, retry, re-sync), audytu enumow (DB bez wartosci spoza PHP enuma), smoke CRUD Filament 5.
Bramka jakosci kazdej warstwy.

**Deliverables (skrot):** tests/Unit/Enums/*; ValidateGroundingTest (macierz fail-closed); InfraStatusSeparationTest;
StatusDecisionTableTest; MultiUnitRenderTest; Migrations/SchemaIntegrity+CascadeZones+RegistryVersioning+GenerationRetry;
Filament/QuestionsResourceTest+AnswerDraftsResourceTest; AuditEnumsCommand + test; factory wszystkich modeli; test
doubles (FakeCandidateRetriever); **testsuite Database na MySQL 8.4** (sqlite NIE odda CHECK/cascade/ULID).

**Kroki:** 1) dwa testsuite w phpunit.xml (Database=mysql) 2) testy enumow rownolegle z definicjami 3) test doubles +
factory 4) macierz fail-closed walidatora (rdzen) 5) StatusDecisionTable + MultiUnit 6) testy migracji na MySQL
7) AuditEnumsCommand 8) smoke Filament 5 9) spiac w bramke.

**Bramka:** `php artisan test --compact` (oba testsuite) GREEN; `--testsuite=Database` na MySQL 8.4 GREEN; macierz
walidatora (kazda galaz); InfraStatusSeparation; CascadeZones (0 sierot, RESTRICT, SET NULL); RegistryVersioning;
audit-enums (exit 0 czysto / exit!=0 po raw INSERT); smoke Filament (F5 API); pint; `grep -cP '[ÃÄÅ]'`==0.

**Ryzyka:** sqlite NIE odwzorowuje MySQL-only (CHECK/ON DELETE/ULID — falszywie zielone) -> testsuite Database;
niedeterminizm (deterministyczny fake; replay = eval-runner); wyprzedzenie zamrozenia (testy migracji przy baseline);
Filament 5 API drift (vendor/); klasyfikator maly model (fake); zaleznosc od warstw (przyrostowo); CI matrix 8.2/8.5.

**Odlozone:** zywy replay N-krotny (eval-runner); testy in-panel (cross-tenant); fuzzing schematu; klastrowanie pytan;
pelne pokrycie Filament; benchmarki latencji; testy PII (osobny zestaw); weryfikacja prompt-caching (telemetria).

**Odniesienia:** kontrakt A/B/C/D/E/F/I; konwergencja (selected_generation_id FK, 3 strefy, registry, CHECK
jednokierunkowe, enumy PHP-backed+VARCHAR); konwencje (Action, enumy, Filament 5, throttle).

---

## 5. MACIERZ Etap x Filar

> `B` = budowany/glowny w etapie; `+` = dotkniety/wspierajacy; pusto = poza etapem.

| Filar \ Etap | 0 | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12 | 13 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| db | B | + | B |  |  |  |  | + |  |  |  |  | + |  |
| corpus | + | B |  | B |  |  |  |  |  |  |  |  |  | + |
| retrieval | + | + |  |  | B | + |  | + |  |  |  | + |  |  |
| client | + | + |  |  |  | B | + | + |  |  |  | + |  |  |
| validator | + | + |  |  |  | + | B | + |  | + |  |  |  |  |
| askdocs | + | B |  |  |  |  |  | B | + |  | + |  |  |  |
| ui |  | + |  |  |  |  |  |  | B |  |  |  |  |  |
| curation |  | + |  | + |  |  |  |  | B |  |  |  |  |  |
| eval |  | + (szkielet) |  |  | + | + |  |  |  | + | + | B |  | + |
| sec-injection | + |  |  |  |  |  | + |  | + | B |  | + |  | + |
| sec-cost | + |  |  |  |  |  |  | + |  |  | B | + |  | + |
| retention | + |  |  |  |  |  |  |  |  |  |  |  | B | + |
| deploy |  |  |  | + |  |  |  |  |  |  |  |  | + | B |
| tests |  | + | B | + | + | + | + | + | + | + | + | + | + | + |

**`[FIX]`** Etap 1 zawiera **szkielet eval-runnera** (stub adaptera + taksonomia + dataset) rownolegle,
aby bramki Etapow 9/10 (klasy eval) mialy na czym stanac (kontrakt I).

---

## 6. ZALEZNOSCI + otwarte decyzje

### Graf zaleznosci (skrot)
~~~~
Etap 0 (decyzje papierowe: schema/enumy/ADR)
   |
   v
Etap 1 (prototyp pionowy temp-DB) --- szkielet eval-runnera [FIX]
   |
   v
Etap 2 (baseline v1 + answer_drafts minimal [FIX]) --- testsuite Database (MySQL 8.4)
   |
   +--> Etap 3 (corpus) --- trigger answer_drafts.expired (tabela z Etapu 2 [FIX])
   +--> Etap 4 (retrieval) --- estimator deterministyczny [FIX]; kalibracja -> Etap 11
   +--> Etap 5 (client) ----- smoke trasy; cache [FIX] -> Etap 11
   +--> Etap 6 (validator) -- fail-mode klasyfikatora ML [FIX]
            |
            v
        Etap 7 (askdocs) --- DTO Result ZAMROZONY [FIX 7->8]
            |
            v
        Etap 8 (ui + curation)
            |
   +--------+--------+
   v                 v
Etap 9 (sec-injection)   Etap 10 (sec-cost)
   |  klasy eval na stubie [FIX]  |  kalibracja -> Etap 11 [FIX]
   +--------+--------+
            v
        Etap 11 (eval + pre-launch replay) --- DOMYKA kalibracje 9/10 + cache (1) [FIX]
            |
            v
        Etap 12 (retencja/RODO) --- opcja retencji = ta sama co deploy [FIX]
            |
            v
        Etap 13 (deploy + 10 warunkow wejscia) --- tryb instancji potwierdzony [FIX]
~~~~

### `[FIX]` Rozstrzygniecia zaleznosci (z KRYTYK-LUKI)
- **Wlasnosc migracji/modeli:** filar **db = JEDYNY wlasciciel** (corpus/askdocs/curation usuwaja deklaracje
  migracji ze swoich deliverables; zostaja tylko Action/komendy).
- **Enumy kolizyjne:** JEDEN plik na enum — `CorpusStatus` -> corpus; `InfraStatus` -> jeden plik wspoldzielony
  (client/validator: parse; sec-cost: retry/koszt).
- **Odwrocona zaleznosc 9/10 <-> 11:** szkielet runnera + klasy eval rownolegle z Etapem 1/5; Etapy 9/10 wymagaja
  KLAS na stubie, kalibracja liczbowa odsetka/progow = Etap 11.
- **answer_drafts.expired (3 <-> 8):** minimalna migracja w baseline Etapu 2; pelny CRUD/UX w Etapie 8.
- **DTO AskDocs (7 -> 8):** zamrozony na koncu Etapu 7 jako warunek wejscia do Etapu 8.
- **Estimator (4 -> 11):** Etap 4 deterministyczny (bez sieci); kalibracja vs usage.prompt_tokens w Etapie 11.
- **content_hash vs content_snapshot:** rozroznione; jedna strategia retencji (rekomendacja opcja A) wspolna dla
  retention + deploy.
- **Cache (G):** osobna twarda bramka >=4096 tok (Etap 1/11), nie warunkowe pominiecie.

### Znane OTWARTE decyzje (sek. 13 v0.5)
- **DECYZJA #1 (zakres):** PUBLICZNY (zamrozona dla v1); in-panel (tenant_id/user_id) addytywnie.
- **DECYZJA #3 (koszt/wolumen):** NIEROZSTRZYGNIETA -> progi budzetu/breakera/estimatora = placeholdery;
  kalibracja pre-launch replayem (Etap 11). Blokuje publiczny start, nie budowe.
- **Retencja — liczby dni:** NIEROZSTRZYGNIETE; ustalona tylko RELACJA korpus>=logi>=messages; kalibracja na wolumenie z owner.
- **Granularnosc answer-unit:** NIEROZSTRZYGNIETA (1 sekcja=1 unit vs multi-unit); rozstrzyga ekstraktor (prototyp); NIE blokuje schematu DB.
- **DECYZJA #6 (pepper owner_token):** **`[v0.5]`** token format WERSJONOWANY `v<key_version>.<token>` (backend
  wybiera pepper PRZED lookupem — sam `key_version` w DB nie wystarcza); cookie Secure/HttpOnly/SameSite; procedura rotacji operacyjna do ustalenia.
- **`[FIX]` content_snapshot vs korpus>=logi:** rozstrzygniecie w ADR Etapu 0 (rekomendacja opcja A).
- **`[FIX]` tryb instancji (single vs multi):** rozstrzygniecie w ADR Etapu 0/10; warunek wejscia do prod (Etap 13).
- **`[FIX]` fail-mode klasyfikatora ML:** fail-OPEN (dostepnosc) vs fail-CLOSED (odrzut); ADR Etapu 0/6; v1 = stub regex-only.

---

## 7. CO NIE JEST w v1 (swiadomie odlozone)

- **Retrieval wektorowy / Qdrant (etap 2)** — MySQL 8.4 vanilla nie ma wektorow (osobna usluga, nie migracja
  bazy transakcyjnej). Trigger: recall@k spada w korelacji z synonimy/literowki/jezyk-potoczny.
- **Retrieval leksykalny (etap 1)** — szkielet teraz, implementacja proaktywnie przy progu corpus_tokens
  (lost-in-the-middle). Wybor MySQL FULLTEXT vs MiniSearch + lematyzator PL nierozstrzygniety.
- **Wariant in-panel (tenant_id/user_id, autoryzacja-przed-retrievalem, cross-tenant gating)** — DECYZJA #1
  zamrozona PUBLICZNY; delta zachowana, dodawana addytywnie.
- **Klastrowanie semantyczne pytan (embeddingi)** — v1 = exact-match `normalized_question_hash`.
- **Generative + grader (LLM-judge) grounding** — v1 = wybor answer-unit; **`[v0.5]` provenance/integralnosc
  „by construction", trafnosc/kompletnosc = EMPIRYCZNE** (nie „entailment by construction").
- **`[v0.5]` Semantyka zaleznosci jednostek** (`requires[]`/`supersedes[]`/`exclusive_group`/`valid_from-to`) —
  v1 multi-unit = zbior renderowany obok siebie bez sygnalu prerekwizytu (F-15, znane ograniczenie, nie ukryta luka).
- **approved_answers jako produkcyjne zrodlo** — USUNIETE z modelu; curation idzie przez docs+re-index (single-source).
- **Few-shot wstrzykiwanie zatwierdzonych wzorcow** — odlozone do mierzalnego progu.
- **Historia rozmow per-przegladarka (pelny widok)** — v2; w v1 owner_token tylko wiaze wiadomosci/oceny.
- **Streaming odpowiedzi do UI** — v1 render synchroniczny calej odpowiedzi po walidacji.
- **Maly model w klasyfikatorze wyjscia (warstwa 2)** — v1 regex-only za flaga; eskalacja po zmierzonych incydentach.
- **Detektor ML prompt injection / CAPTCHA / per-anon-token throttle** — v1 = regex + throttle per-IP+global; v2/po progu.
- **CI-gate eval na kazdy commit** — v1 = auto-uruchamianie klas przy deployu + pre-launch replay.
- **Wspoldzielony magazyn artefaktu (S3-like) / blue-green** — v1 storage lokalny ze sciezka z config (abstrakcja);
  dlug przed publicznym startem przy multi-instancji.
- **KMS/HSM dla peppera, twarda anonimizacja PII** — v1 pepper w .env (wersjonowany), PII best-effort.
- **Prompt caching > etap 0** — od etapu 1 cache wylaczyc (prefiks niestabilny).

---

> Dokument podlega zasadzie GO: bez zmian w kodzie do jawnej akceptacji. Bramki weryfikowalne
> (komenda/test/metryka), nie daty. Po prototypie pionowym -> baseline v1 -> tylko migracje addytywne.

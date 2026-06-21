# BIELIK_INTEGRATION.md — roadmap v2.4 (lokalny LLM w Laravel 12)

> **v2** — po zewnętrznym review (kierunek 8.5/10, GO po korektach). Główna zmiana:
> **usunięty automatyczny LAN sweep z request-path** → stabilna nazwa / heartbeat;
> realny **circuit breaker** zamiast probe-cache; **typowane błędy**; **named providers**
> od początku; **bezpieczeństwo Ollamy przed routingiem**.
> **v2.1:** domknięte rozstrzygnięcia kontraktowe **A–D** (idempotencja przy fallbacku, enum statusów,
> anty-SSRF heartbeatu, cold-start) — patrz sekcja „Rozstrzygnięcia kontraktowe (Faza 0)".
> **v2.2:** korekty po trzecim audycie — 10 znalezisk: rezerwacja przed wywołaniem AI (A2),
> deadline-aware timeouty (D), `keep_alive` wyłącznie env serwera + `/api/chat` (D), fallback po
> `auth_error` local primary (B/kontrakt), TOCTOU DNS + reverse proxy (C), HMAC replay (C),
> rozdzielony `TransportCircuitBreaker` / `QualityCircuitBreaker` (Faza 3), `config/askdocs.php`
> (unikamy kolizji z `laravel/ai`), telemetria odtwarzalna (Faza 6), moduł domenowy (best practice 10).
> **v2.3:** decyzje organizacyjne (przed kodem) — **moduł `App\AskDocs`** + port `AnswerUnitSelector`
> + rename `config/ai.php`→`config/askdocs.php` + mapa 3 wymiarów statusu (sekcja „Rozstrzygnięcia organizacyjne").
> **v2.4:** po 4. audycie + GLM 5.2 — grounding wewnątrz próby (Q), rezerwacja CAS (R), node-identity≠digest+TLS (S),
> OpenRouter strict/require_parameters (T), LocalOnly w routerze (U), Redis fail-closed (V), taksonomia statusów (W),
> hierarchia wiążąca SCOPE_V1 > backlog (P). Patrz „Rozstrzygnięcia v2.4".
> Cel niezmienny: Bielik jako **hybryda** (lokalny gdy żyje → OpenRouter w fallbacku),
> nie zamiennik. Wiedza operacyjna: `memory/project_bielik_integration`.
>
> **Zasada nadrzędna:** *model nie jest źródłem prawdy — wskazuje kandydata,
> Laravel podejmuje autoryzowaną i walidowaną decyzję.*

## Co potwierdzone (spike techniczny 0A ✅ · Faza 0 kontraktowa: W TOKU)
- Ollama **`/v1/chat/completions` + `response_format: json_schema`** spełnia kontrakt AskDocs
  (`bielik-11b-v3-q80`): trafia w jednostkę, abstynuje poza zakresem, sub-sekunda, `cost`=brak.
- Wniosek: adapter Ollamy to cienka warstwa nad istniejącym `callModel()`.

## Architektura docelowa
```
Chat → AskDocs
  ├─ CandidateRetriever        (jeden retriever; ten sam generation_context dla obu providerów)
  ├─ AnswerUnitSelector (PORT domenowy)  ← AskDocs zależy od TEGO portu
  │    └─ FailoverSelector (router + breaker + deadline) → ChatModel (kontrakt techniczny):
  │         ├─ OllamaChatModel      (payload Ollamy w adapterze)
  │         └─ OpenRouterChatModel  (blok `provider` budowany TU, nie w AskDocs)
  ├─ ModelRouter + CircuitBreaker
  │    closed → wywołaj Bielika; błąd fallbackowalny → open + OpenRouter
  │    open   → od razu OpenRouter ;  half-open → 1 request testowy
  ├─ BielikEndpoint            (po NAZWIE/heartbeat, nie po IP; cache TTL)
  └─ GroundingValidator        (answer_unit_id ∈ generation_context, atomowy) — wspólny dla obu
        → render zatwierdzonej jednostki (dosłownie) + link
```

## Zasady przyjęte z review
1. **Brak skanowania LAN w aplikacji.** Adres Bielika z: (a) **rezerwacji DHCP + stabilnej nazwy**
   (`bielik.home.arpa` / Docker DNS), lub (b) **podpisanego heartbeat** (Bielik POST-uje swój
   endpoint do Laravela; Redis + krótki TTL). Sweep **tylko** jako `bielik:discover` (artisan, OFF).
2. **Circuit breaker, nie probe-cache.** Stany closed/open/half-open, próg błędów, atomic lock na
   half-open (anty-thundering-herd), reset po sukcesie. **Nie probe'ować przed każdym wywołaniem** —
   próbować realnego callu i reagować na błąd.
3. **Typowane wyjątki, nie `?null`.** `out_of_scope` = wynik domenowy. Fallback **tylko** dla błędów
   fallbackowalnych (patrz niżej).
4. **Named providers + routing policy** w configu od początku (nie `driver`). Payload provider-specific
   w adapterze.
5. **Jeden `generation_context`** dla Bielika i fallbacku (porównywalność, repro, debug).
6. **Bezpieczeństwo Ollamy PRZED routingiem** (Ollama API bez auth): firewall tylko z hosta Laravel /
   reverse proxy + klucz/HMAC, bind, rate-limit, ograniczone endpointy.
7. **Współbieżność i timeouty** dopasowane do 1× GPU (niżej).
8. **Tożsamość modelu po `digest`/tagu**, nie substring „bielik".
9. **Telemetria odtwarzalna** (kolumna `metadata` JSON), nie samo `model`.
10. **Eval mierzalny** (golden dataset PL; rozdzielić recall retrievera od trafności selekcji).

## Świadomie ODŁOŻONE (YAGNI dla v1: publiczne docs, single-tenant, ~91 jednostek)
- **Autoryzacja per-tenant** (`∈ authorized_units_for_user`) — docs publiczne, brak ról/tenantów.
  Zostaje *hook* w walidatorze (gdyby kiedyś multi-tenant), nie implementujemy.
- **Silnik polityk prywatności routingu** — wystarczy prosty flag `LocalOnly` (pytania i tak
  redagujemy z PII; korpus publiczny). Pełny silnik = później.
- **Ciężki retriever** (dense/hybrid RRF/reranker) — przy 91 jednostkach przerost. Embeddingi przez
  `bge-m3` (jest na Ollamie) dopiero gdy Recall@k tego zażąda.
- **`laravel/ai` SDK** — opcjonalny spike (v0.8.x, 0.x = churn); na teraz własne, małe adaptery.
- **LiteLLM gateway** — gdy pojawi się druga aplikacja / więcej modeli.

## Rozstrzygnięcia kontraktowe (Faza 0 — wybrane „najlepsze")
Domknięcie luk spójności wykrytych w przeglądzie (A–D). To są **decyzje wiążące** przed kodem.

**A. Idempotencja + fallback → JEDNA generacja na submit.**
Przy fallbacku (Bielik fail → OpenRouter) **nie** tworzymy dwóch wierszy `generations` (kolizja
`operation_id UNIQUE`). Powstaje **jeden** wiersz = provider, który FINALNIE odpowiedział
(`model`, `infra_status`, tokeny, `cost`). Historia prób ląduje w `generations.metadata.attempts[]`
(`{provider, infra_status, latency_ms, error_class}`). `operation_id` UNIQUE i check idempotencji —
bez zmian. (Rozwiązuje też E: 1 wiadomość ↔ 1 generacja.)

**A2. Rezerwacja przed wywołaniem AI — luka do zamknięcia w Fazie 1.**
Obecny check `Generation::where('operation_id')->first()` chroni przed duplikatem w DB, ale
**nie przed podwójnym wywołaniem AI**: dwa równoległe requesty z tym samym `operation_id` oba
przejdą check (żaden nie znalazł rekordu jeszcze) i oba wywołają model. Fallback z Bielika na
OpenRouter szczególnie narażony — dwa wywołania różnych providerów to realna ścieżka.
Wymagany protokół rezerwacji (Faza 1):
1. Przed wywołaniem: atomowy `INSERT generations(operation_id, status='pending', lease_expires_at=now+30s)`.
   Jeśli UNIQUE violation → status `completed` = zwróć istniejący wynik; status `pending` = zwróć HTTP 202.
2. Po wywołaniu AI: `UPDATE ... SET status='completed'/'failed'`.
3. Osierocone `pending` (crash PHP) → scheduler zwalnia po `lease_expires_at`.
Nie w v1 (SCOPE_V1 backlog), ale **Faza 1 Bielika wchodzi z tym protokołem** — bo dwa adaptery = dwa możliwe wywołania.

**B. Rozszerzony enum `InfraStatus` + mapowanie wyjątków.**
Dodać: `provider_unavailable`, `provider_overloaded`, `auth_error` (enum = string, migracja niepotrzebna).
Mapowanie wyjątek → status → fallback:
| Wyjątek | InfraStatus | Fallback |
|---|---|---|
| `ModelUnavailableException` | `provider_unavailable` | ✅ |
| `ModelOverloadedException` (503/queue) | `provider_overloaded` | ✅ |
| `ModelTimeoutException` | `provider_timeout` | ✅ wg polityki |
| `ModelProtocolException` (zły JSON) | `invalid_schema` | ✅ + alert |
| `ModelAuthenticationException` (primary local) | `auth_error` | ✅ fallback do remote + alert krytyczny |
| `ModelAuthenticationException` (remote) | `auth_error` | ❌ alert krytyczny, brak dalszego fallbacku |
| `ModelValidationException` (bad request) | `invalid_schema` | ❌ alert konfiguracji |
`infra_status` generacji = wynik **finalny** (`completed`, gdy fallback się udał; terminalny błąd, gdy oba padły); statusy prób w `metadata.attempts`. `out_of_scope` = wynik domenowy, nigdy wyjątek.
> **Uwaga:** `auth_error` localnego primary nie powinno blokować dostępności — użytkownik dostaje
> odpowiedź przez OpenRouter, a alert krytyczny sygnalizuje błąd konfiguracji Bielika.

**C. Anty-SSRF: DNS-first + obowiązkowa allowlista.**
Domyślnie **stabilna nazwa** (rezerwacja DHCP + DNS / Docker DNS) — zero powierzchni inbound.
Heartbeat **tylko opcjonalnie**, zawsze **HMAC-podpisany**. Niezależnie od mechanizmu: rozwiązany
endpoint musi przejść **allowlistę** (`BIELIK_ALLOWED_CIDR`, np. `192.168.10.0/24`, + port `11434`)
**oraz** potwierdzić **tożsamość** (oczekiwany `digest` w `/api/tags`) **zanim** poleci jakikolwiek
ruch użytkownika. Adres spoza allowlisty = traktuj jak „Bielik down" → OpenRouter. (Chroni przed
wysłaniem pytań usera do podstawionego endpointu.)

**C2. TOCTOU DNS — rekomendowane rozwiązanie: reverse proxy.**
Schemat „rozwiąż DNS → sprawdź IP → wyślij request do nazwy hosta" jest podatny na race condition:
klient HTTP może ponownie rozwiązać DNS między krokiem 2 i 3, a zmieniony wynik ominąłby allowlistę
(wariant DNS rebinding). Profesjonalne opcje: (a) pinning połączenia do zweryfikowanego IP z poprawnym
`Host`/SNI, (b) **najprostsze i rekomendowane dla tego projektu:** Laravel → **reverse proxy lokalny**
(nginx/caddy na tym samym hoście) → `localhost:11434` — Laravel nigdy nie widzi dowolnego URL,
proxy jest skonfigurowany statycznie. Wyłączyć automatyczne redirect-following w kliencie HTTP.

**C3. Heartbeat HMAC — ochrona przed replay attack.**
Sam HMAC treści nie wystarcza: przechwycony poprawny heartbeat można wysłać ponownie.
Podpis musi obejmować: `node_id + timestamp + nonce + body_hash + key_id + metoda HTTP + ścieżka`.
Laravel akceptuje: clock skew ≤ 30 s + nonce jednorazowy (Redis TTL = 2× skew) + porównanie
`hash_equals()`. Rotacja kluczy przez `key_id`. Heartbeat przekazuje stan node'a i digest —
**nie dowolny URL do wywołania** (endpoint pochodzi z configu / tożsamości node'a, nie z treści heartbeat).

**D. Cold-start: keep-warm zamiast długich timeoutów.**
`OLLAMA_KEEP_ALIVE=30m` (env serwera, lub `-1`) + **scheduled warmup** (ping co ~10 min) → user
rzadko trafia na zimny model.
**`keep_alive` jako parametr requestu do `/v1/chat/completions` nie jest obsługiwany przez Ollamę**
— dotyczy wyłącznie natywnych endpointów `/api/chat` i `/api/generate`. Warmup = osobny klient
infrastrukturalny (`/api/chat`), nie `callModel()`.
**Deadline-aware timeouty** (nie addytywne, nie niezależne): jeden `overall_deadline` ~30 s
propagowany przez cały workflow; timeouty per-attempt to **wycinki** tego budżetu, nie osobne limity:
- `connect` ~2 s
- `health` check ~2 s
- local attempt (model w VRAM po warmupie) ~**8–12 s** (25 s tylko jeśli cold-start i brak warmup)
- remote fallback = `overall_deadline - elapsed`
Router przed każdą próbą: `remaining = overall_deadline - elapsed`; jeśli `remaining < MIN_FALLBACK_BUDGET`
(~6 s) → nie próbuj fallbacku, zwróć degradację. Bielik z limitem 25 s zostawia ~5 s dla OpenRouter —
za mało na DNS + TLS + kolejkę + odpowiedź; model w VRAM powinien odpowiedzieć w 8–12 s.

## Rozstrzygnięcia organizacyjne (Faza 0 — wiążące PRZED kodem)

**M. Granica modułu: `App\AskDocs\` (świadome, OGRANICZONE rozluźnienie `BACKEND_CONVENTIONS §7`).**
Bielik to nie metoda ani `BielikService` — to kolejny **adapter** modułu **AskDocs**. Komplet (2 adaptery
+ router + breaker + resolver + retriever + telemetria) to już nie jedna Akcja → wydzielamy moduł.
Struktura **LEKKA** (godzi audyt z §7/YAGNI):
```
app/AskDocs/
  AnswerQuestion.php              # use case — cienki orchestrator (wejście)
  Contracts/
    AnswerUnitSelector.php        # PORT domenowy: selectUnits(AskDocsRequest): Selection
    CandidateRetriever.php
    ChatModel.php                 # kontrakt techniczny (tylko wewnątrz adapterów/routera)
  Adapters/
    Ollama/OllamaChatModel.php
    OpenRouter/OpenRouterChatModel.php
    Routing/FailoverSelector.php  # implementuje AnswerUnitSelector (router+breaker+fallback+deadline)
    Retrieval/{FullCorpus,Lexical}Retriever.php
    Discovery/{Dns,Heartbeat}EndpointResolver.php
    Resilience/{Transport,Quality}CircuitBreaker.php
  Security/{EndpointAllowlist,HeartbeatVerifier}.php
  Exceptions/Model*Exception.php
```
Bind w **`AskDocsServiceProvider`** (`bootstrap/providers.php`); constructor injection, bez globalnej fasady.

**Świadomie ODRZUCAMY z propozycji audytu (per §7/YAGNI):**
- **Bez `GenerationRepository`** → Eloquent bezpośrednio (`Message`/`Generation` już są).
- **Bez biblioteki DTO/CQRS** → `ModelRequest`/`ModelResult`/`Selection` jako `readonly class` lub array-shape.
- **Bez pełnego Domain/Application/Infrastructure** → płaski `Contracts/` + `Adapters/` wystarcza dla 1 use-case.

Bierzemy **granicę modułu + port**; ciężką ceremonię DDD — nie. **Dlaczego port `AnswerUnitSelector`,
nie surowy `ChatModel`:** domena nie potrzebuje „czatu", tylko *„wybierz jednostkę z kandydatów albo
out-of-scope"*; `ChatModel` to detal infra wewnątrz adaptera — nie przecieka do `AnswerQuestion`.

**N. Rename `config/ai.php` → `config/askdocs.php` (Faza 1).**
Powód: `laravel/ai` SDK publikuje własny `config/ai.php` (kolizja przy spike'u — Wariant B). Zakres:
przenieść klucze do `config/askdocs.php` (sekcje `providers/routing/retrieval/timeouts/circuit_breaker/
privacy/telemetry/endpoint_security`); zaktualizować obecny `AskDocs` + `chat:assistant-smoke` + testy.
`.env` bez zmian (te same zmienne, czytane w nowym configu). Refaktor działającego v1 — pokryty testami.

**O. Mapa 3 wymiarów statusu (zamyka audyt #2 — NIE scalać w jeden enum).**
| Wymiar | Kolumna / pole | Wartości |
|---|---|---|
| **Workflow** (cykl życia generacji) | `generations.status` (A2 lease) | pending · processing · completed · failed |
| **Outcome** (wynik domenowy) | `messages.product_status` (`ProductStatus`) | answered · abstained · needs_clarification |
| **Attempt** (próba providera) | `generations.metadata.attempts[].status` + `generations.infra_status` (finalna) | completed · provider_unavailable · provider_overloaded · provider_timeout · invalid_schema · auth_error |
`ResponseType` modelu (`answer/clarification/abstention/out_of_scope`) = surowa odpowiedź → mapuje się
na **Outcome**, nie jest błędem ani attempt-statusem.

## Rozstrzygnięcia v2.4 (po 4. audycie + GLM 5.2 — wiążące)

**P. Hierarchia wiążąca / kierunek synchronizacji.** `docs/SCOPE_V1.md` + ten dokument = **wiążące dla v1**;
`AI_ASSISTANT_DESIGN.md v0.5` + `ROADMAP.md` = **backlog hardeningu (SUPERSEDED dla v1)**. Stąd „konflikty"
B1–B4/B18/B20 (GLM) to **nieaktualność backlogu**, nie zmiany w bieliku: zbudowany `generations` ma
`operation_id` UNIQUE i **nie** ma `attempt_count`/`selected_generation_id` → **1 wiersz + `metadata.attempts[]`
(A) zostaje**; model OpenRouter = **`openai/gpt-5.4-nano`** (`config/ai.php`, zacommitowane); `security_verdict`/
ML-gate z design-doca **nie dotyczy** — nasz anty-injection = gate frontmatter `assistant:true`.

**Q. (BLOKER) Grounding WEWNĄTRZ każdej próby providera.** Walidacja `answer_unit_id ∈ generation_context`
+ spójność pól (`answer` bez ID = błąd; `out_of_scope` z ID = błąd; >1 jednostka gdy kontrakt na 1 = błąd)
dzieje się **w próbie, przed uznaniem za sukces** — nie jako warstwa po. Inaczej zła jednostka Bielika nie
wyzwala fallbacku (łamie rdzeń). Nowy `ModelGroundingException` → `grounding_violation` (**Quality** breaker,
fallbackowalny). Struktura: `FailoverAnswerUnitSelector{ LocalSelector{Ollama + SelectionValidator},
RemoteSelector{OpenRouter + SelectionValidator} }`. Końcowy `GroundingValidator` zostaje jako 2. bezpiecznik.

**R. (BLOKER) Rezerwacja z CAS-takeover (rozszerza A2).** Kolumny: `status, processing_owner,
processing_started_at, lease_expires_at, request_fingerprint, execution_attempt` + `INDEX(status, lease_expires_at)`.
Przepływ: atomowy INSERT `status=processing, owner=uuid`; przy UNIQUE violation → `completed`=zwróć wynik ·
`processing`+lease ważny=**202** · `processing`+lease wygasły=**CAS takeover** (`UPDATE … WHERE status=processing
AND lease_expires_at<NOW() AND processing_owner=:old`) · `failed`=polityka retry. **Lease 45–60 s** (> `overall_deadline`).
Wywołanie LLM **poza transakcją**. `request_fingerprint` = HMAC(conversation_id + actor + znormalizowane pytanie
+ typ + wersja kontraktu); inny fingerprint na tym samym `operation_id` → **409**. Kontrakt 202: `Retry-After`
+ `GET /chat/operations/{id}` + autoryzacja + limit pollingu. Słownictwo: „**1 generacja w DB + max 1 aktywny
wykonawca**" (NIE „dokładnie 1 wywołanie AI" — exactly-once niemożliwe bez idempotencji po stronie providera).

**S. Tożsamość serwera ≠ digest + topologia.** node-identity: mTLS / cert proxy / HMAC-per-node / WireGuard
/ firewall. model-identity: dokładny tag + digest + family + quantization. Zmiana digestu przy okresowym
readiness → **unieważnij endpoint** (→ OpenRouter) aż re-allowlist; nowy digest = nowy wpis breakera.
Topologia: **localhost** → reverse proxy `→127.0.0.1:11434`; **cross-host (Bielik na osobnym GPU)** →
IP-pinning + poprawny Host/SNI + **TLS/mTLS** (sam reverse proxy NIE znosi cross-host SSRF — GLM B7;
HMAC nie szyfruje treści pytań).

**T. Kontrakt OpenRouter (obowiązkowy w `OpenRouterChatModel` + contract-test).** `provider.require_parameters=true`
+ `response_format.json_schema.strict=true` + `provider.data_collection=deny` (+ `zdr=true`, jeśli potwierdzone
dla modelu/endpointów). **Ollama strict NIE jest podstawą zaufania** — output zawsze untrusted, jedyną granicą
jest walidator backendu (`∈ context`). `ChatModel` **zastępuje** `AssistantClient` z design-doca; `OpenRouterChatModel`
inkorporuje provider-pinning + mapowanie InfraStatus.

**U. `LocalOnly` w routerze od v1.** Min. `routing.remote_fallback_allowed` (default `local_preferred`);
polityka `local_only` → przy awarii Bielika **zero** żądań do OpenRoutera + kontrolowana degradacja. Router
egzekwuje to **niezależnie** od redakcji PII. Test: LocalOnly + Bielik down ⇒ OpenRouter dostaje 0 żądań.

**V. Redis fail-closed.** Redis down → nonce odrzucony (heartbeat „nieważny" = Bielik down) **i** semafor
niedostępny → **natychmiastowy fallback** OpenRouter. Spójne z „Bielik down → OpenRouter" (ADR).

**W. Taksonomia statusów próby (rozbicie `invalid_schema`).** `protocol_error` (zły output modelu — Quality,
fallback) · `request_invalid` (złe żądanie aplikacji — **bez** fallbacku, alert) · `context_too_large`
(`ContextWindowExceeded` — fallback do większego ctx / przytnij retrieval) · `grounding_violation` (Q).
HTTP: `429 → RateLimited` (**nie**-retryable) vs `503/overload → ProviderOverloaded` (retryable/fallback).
PascalCase zgodnie z istniejącym enumem (`ProviderUnavailable` już istnieje — nie dublować snake_case).

**X. Pozostałe (z obu audytów).**
- **Warmup CYKLICZNY** — spike (2026-06-22) potwierdził: `/v1` **NIE** resetuje `keep_alive` (`expires_at`
  bez zmian po callu). Więc `OLLAMA_KEEP_ALIVE=30m` env serwera **+** scheduled warmup przez `/api/chat`
  co < okno (`withoutOverlapping` + `onOneServer`) — nie jednorazowo.
- **Degradacja** zdefiniowana: `InfraStatus=ProviderTimeout`/nowy `DeadlineExceeded`, `ProductStatus=abstained`,
  treść = komunikat awaryjny PL + deep-link do wyszukiwarki docs; wiersz `generations` zapisany (telemetria), `message_units` puste.
- **Twardy budżet** retrievera: max jednostek **i** max tokenów; kolejność `score DESC, id ASC`; bez ucinania
  jednostki w połowie (całe jednostki póki w budżecie); mały `max_tokens` outputu; `OLLAMA_MAX_QUEUE=1`.
- **Odtwarzalność → audytowalność**: jest `content_hash` w `generation_context`; **recheck hashu PRZED renderem**
  (zmiana treści między retrievalem a renderem → render snapshotu albo abort). „Pełna odtwarzalność" tylko z wersjonowanym korpusem (backlog).
- **Koszt** = providera **finalnego** (nie suma); per-próba w `metadata.attempts[].cost`.
- **Klucze breakerów**: Quality `{use_case, provider, model_digest, prompt_version, output_schema_version}`;
  Transport `{provider, endpoint, model_digest}` + progi/okno/half-open (atomic lock). Active `/api/version|tags`
  **nie** zamyka breakera — dopiero udany half-open.
- **Dystrybucja HMAC**: `.env` na serwerze Bielik (ręcznie / kanał Tailscale-SSH), rotacja przez `key_id`
  (dodaj nowy → potwierdź → usuń stary).
- **Drobne**: `selectAnswerUnit()` (1 wynik), readonly-class na granicach modułu (nie array-shape), monotoniczny
  `hrtime()` na deadline + czas bazy (`CURRENT_TIMESTAMP`) na lease, konwersja ns→ms metryk Ollamy, bind w
  `AskDocsServiceProvider` (best-practice 2 do poprawienia).

## Roadmap fazowy (bramki, nie daty; GO przed kodem)

### Faza 0 — kontrakty + bezpieczeństwo + benchmark (przed kodem)
- Finalny `json_schema`; semantyka `out_of_scope`; **lista wyjątków fallbackowalnych** (niżej).
- Plan bezpieczeństwa Ollamy (firewall/proxy/auth) i **plan stabilnej nazwy/heartbeat**.
- **Golden dataset PL** + progi `Recall@k` i `p95`.
- **Bramka:** zasady spisane jako testowalne kontrakty; sposób bezpiecznego wystawienia Ollamy ustalony.

### Faza 1 — moduł AskDocs + nazwane adaptery (tu Bielik staje się testowalny)
- **Moduł `App\AskDocs\`** (decyzja M): przenieś obecny `App\Actions\AskDocs`/`Corpus\*`/`BuildCorpus`
  (testy zielone) + `AskDocsServiceProvider`; port `AnswerUnitSelector`, kontrakt `ChatModel`,
  adaptery `OllamaChatModel`/`OpenRouterChatModel`.
- `config/askdocs.php` (rename z `config/ai.php` — decyzja N; kolizja z `laravel/ai` SDK):
  **named providers + routing** (nie `driver`); payload OpenRoutera w jego adapterze.
  Zmienne `.env` odczytywane wyłącznie w configu (po `config:cache` Laravel nie ładuje `.env` na requestach).
- Migracja: `generations.metadata` (JSON) + `generations.status` (workflow: pending/processing/
  completed/failed, **A2**) + rozszerzenie `InfraStatus` (**B**); **reguła „1 generacja/submit"**,
  próby w `metadata.attempts[]` (**A**).
- Statyczny endpoint Bielika w `.env` (resolver dochodzi w Fazie 2) → **flip routingu = test w czacie**.
- **Bramka:** oba adaptery przechodzą **ten sam contract-test suite** (malformed JSON, out_of_scope,
  id spoza kontekstu) — `Http::fake`, bez sieci.

### Faza 2 — stabilny i bezpieczny endpoint (zastępuje stary „sweep")
- Rozwiązywanie po **nazwie** (DHCP+DNS / Docker DNS) **lub** podpisany **heartbeat** → cache (TTL).
- **Zero sweepu w request-path**; sweep wyłącznie jako `bielik:discover` (OFF domyślnie).
- Reverse proxy / firewall / auth przed Ollamą; **tożsamość po digest**; `/api/version` (liveness),
  `/api/tags` (readiness, okresowo).
- **Allowlista adresu (anty-SSRF, C):** rozwiązany endpoint musi pasować do `BIELIK_ALLOWED_CIDR`+port
  i mieć oczekiwany `digest` **zanim** poleci ruch; heartbeat zawsze HMAC; adres spoza allowlisty = „down".
- **Bramka:** zmiana IP nie rusza aplikacji; brak nieautoryzowanego dostępu do Ollamy; ruch nigdy nie
  trafia do nie-zweryfikowanego adresu.

### Faza 3 — router + circuit breaker
- Stany closed/open/half-open; atomic lock; klasyfikacja wyjątków; fallback tylko dla dozwolonych;
  **brak retry** dla błędów walidacji/auth; **wspólny generation_context** dla fallbacku.
- **Dwa niezależne circuit breakery:**
  - `TransportCircuitBreaker` — otwiera: timeout, connection error, HTTP 5xx/503. Klucz: `{provider, endpoint, model_digest}`.
  - `QualityCircuitBreaker` — zlicza: malformed JSON, `ModelProtocolException`. Progowe przekroczenie
    wyłącza konkretny `{provider, model_digest}` bez wpływu na inne modele lokalnie.
  Jeden błąd parsowania JSON **nie otwiera** transportowego breakera. Awaria jednego modelu
  lokalnie nie blokuje innych.
- Aktywny health-check w schedulerze (dodatkowo, nie w request-path).
- **Bramka:** pełna macierz testów awarii obu providerów (up/down/timeout/503/malformed);
  test że `ModelProtocolException` nie otwiera `TransportCircuitBreaker`.

### Faza 4 — retrieval (mierzony)
- Filtr autoryzacji (dla nas: no-op/hook — publiczne), `intents` + **lexical top-k**, pomiar `Recall@k`.
- trigram/fuzzy (PL: odmiana, literówki, brak ogonków) **jeśli** testy wymagają; embeddingi (`bge-m3`) później.
- **Bramka:** zdefiniowany próg `Recall@k` na zbiorze PL osiągnięty; prompt Bielika mieści się w ctx.

### Faza 5 — współbieżność i wydajność (RTX 3090, 1× GPU)
- `OLLAMA_NUM_PARALLEL=1`, mały `OLLAMA_MAX_QUEUE` (domyślne 512 = za dużo), **limiter w Laravelu**
  przed lokalnym providerem, szybki fallback gdy kolejka > próg.
- **Deadline-aware timeouty** (patrz Rozstrzygnięcie D): `overall_deadline` ~30 s → local attempt
  ~8–12 s (model w VRAM po warmupie) → fallback = remaining. **Nie sumuj timeoutów niezależnie.**
  `OLLAMA_KEEP_ALIVE=30m` env serwera; warmup przez natywne `/api/chat`, nie `/v1/chat/completions`.
  Redis semaphore przed local attempt (pojemność 1); wait ≤ 500 ms — przekroczenie → natychmiast fallback.
- **Bramka:** osiągnięte `p95` bez nadmiernego fallback-rate; test 503/przeciążenia.

### Faza 6 — observability + rollout
- `generations.metadata` (JSON): `provider, model, model_digest, route_policy, route_reason,
  fallback_from, fallback_reason, circuit_state, corpus_version, context_hash, prompt_version,
  prompt_hash, output_schema_version, retriever_version, retriever_config_hash,
  context_unit_ids, selected_unit_content_hashes, load_duration_ms, eval_duration_ms,
  total_latency_ms, cost, cost_source, error_class, trace_id`.
  `corpus_version` + `context_hash` + `selected_unit_content_hashes` → pełna odtwarzalność
  bez zapisywania treści promptu; `content_hash` per jednostka już w `generation_context` (istniejąca tabela).
- Canary + **kill-switch per provider**; dashboard fallbacków/błędów.
- **Bramka:** każdą złą odpowiedź da się odtworzyć z telemetrii.

## Kontrakt błędów (Faza 0/1)
| Wyjątek | Fallback? |
|---|---|
| `ModelUnavailableException` | ✅ |
| `ModelOverloadedException` (503/queue) | ✅ |
| `ModelTimeoutException` | ✅ wg polityki |
| `ModelProtocolException` (zły JSON/schema) | ✅ + alert |
| `ModelAuthenticationException` (primary local) | ✅ fallback do remote + alert krytyczny |
| `ModelAuthenticationException` (remote) | ❌ alert krytyczny |
| `ModelValidationException` (bad request) | ❌ (nie ukrywać) |

`out_of_scope` / abstynencja = **poprawny `ModelResult`**, nigdy wyjątek/null.

## Best practices — lokalny LLM w Laravel 12 (skondensowane)
1. **Grounding niezależny od providera** (`json_schema` + walidator `∈ context`) — zwornik.
2. **Strategy/Adapter za interfejsem**, bind w `AppServiceProvider`; AskDocs nie zna API providera.
3. **Config-driven, hosty po NAZWIE/heartbeat** nie IP; typ providera = Enum.
4. **Circuit breaker + degradacja zamiast błędu**; krótkie, rozdzielone timeouty; cache decyzji.
5. **Wolne operacje (discovery/warmup/eval) → scheduler/queue** (`ShouldQueue`, `WithoutOverlapping`),
   nigdy w request-path.
6. **Bezpieczeństwo lokalnego API** (Ollama bez auth) — firewall/proxy/auth zanim wystawisz na LAN.
7. **Bezpieczniki/koszt per provider** (Bielik darmowy, poza budżetem; budżet/kill-switch dla płatnego).
8. **Testy `Http::fake`** dla obu + contract-test suite; zero realnych wywołań w CI.
9. **Telemetria odtwarzalna** (metadata JSON + metryki Ollamy: `load_duration`, `eval_*`).
10. **YAGNI/przyrostowo**; granica modułu = **decyzja M** (`App\AskDocs` + port `AnswerUnitSelector`
    + `config/askdocs.php`; LEKKO: bez Repository/DTO — Eloquent + readonly/array-shape). Bielik =
    konfiguracja modelu, nie nazwa modułu; Ollama/OpenRouter = wymienialne adaptery.

## Warianty architektury
| Wariant | Kod w Laravel | Operacje | Rekomendacja |
|---|---|---|---|
| **Custom Laravel-native (poprawiony)** | średnio | nisko | **teraz** (po usunięciu sweepu + security + breaker) |
| `laravel/ai` SDK (v0.8.x) | mało/średnio | nisko | kandydat do **spike'a** (przypiąć wersję; rdzeń domenowy zostaje nasz) |
| LiteLLM gateway | mało | średnio | gdy ≥2 aplikacje / więcej modeli |

## Ryzyka / decyzje otwarte
- **LAN-only** — chat poza siecią Bielika ⇒ tylko OpenRouter / VPN (Tailscale/WireGuard).
- **q80 vs q6k** — jakość vs szybkość/VRAM (rozstrzyga eval).
- **Cold-start** — pierwszy call ładuje model (`load_duration`); `keep_alive`/warmup w schedulerze.
- **Jakość lexical** dla PL — mierzyć `Recall@k`; ewentualnie fuzzy → embeddingi (`bge-m3`).
- **Prompt injection w docs** — gate human-approval + render verbatim łagodzi; dodać przypadki do evalu.

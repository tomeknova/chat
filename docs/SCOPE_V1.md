# SCOPE v1 — AskDocs (prosty, ale produkcyjny)

> Granica tego, co budujemy w v1. Pełny projekt produkcyjny (po 5 audytach) =
> `AI_ASSISTANT_DESIGN.md` v0.5 + `ROADMAP.md` — traktujemy je jako **backlog hardeningu**.
> v1 = najprostsza wersja, która **bezpiecznie obsłuży realnych klientów publicznie**.

## Zasada
**Simple = mniej ruchomych części** (do zbudowania i utrzymania przez jedną osobę, która się uczy),
**NIE mniej bezpieczeństwa.** Bezpieczniki wymagane przez publiczny ruch zostają — w najprostszej działającej formie.

## Co budujemy (jedno zdanie)
Publiczny, jednostronicowy asystent: user pyta → model **wybiera** zatwierdzoną jednostkę dokumentacji →
backend **renderuje JĄ** (nie zmyśla) + link. Ocena 👍/👎.

## Decyzje zamrożone v1
- **Single-turn** (bez wielotury / kontynuacji clarification).
- **Jeden przypięty endpoint** OpenRouter (`anthropic/claude-haiku-4.5`) + smoke-test strict JSON.
- **Korpus = plik** (nie tabele wersji).
- **PII: redacted-only** — surowego pytania NIE trzymamy.

## Rdzeń: anty-halucynacja (sens projektu — NIE upraszczać)
- Model zwraca `response_type` + `answer_unit_ids[]` (strict JSON).
- Backend renderuje **tylko** jednostkę, którą model widział: `answer_unit_id ∈ generation_context`
  + zgodny `content_hash`. Inaczej → abstynencja.
- Link z korpusu/manifestu, **nie od modelu**.
- Multi-unit **atomowy**: któraś jednostka nie przejdzie → cały zestaw odrzucony (brak ukrytej częściowości).

## Bezpieczniki (zostają — bo publiczne, realni klienci)
| Obszar | Forma v1 (prosta) |
|---|---|
| Koszt / denial-of-wallet | throttle per-IP + **dzienny limit budżetu** + kill-switch AI |
| Injection | **Ty zatwierdzasz docs** (human review = realna granica) + regex na renderowanym body |
| PII / RODO | tylko `redacted_question` + hash; **kasowanie po `owner_token`** |
| Idempotencja | `operation_id` per submit (podwójny klik nie podwaja kosztu) |
| Transakcje | wywołanie OpenRouter **POZA** transakcją (zapisz pytanie → zawołaj → zapisz wynik) |
| Sekrety | klucz tylko w `.env` |

## Model danych — 5 tabel (MySQL 8.4)
~~~
conversations
  id, public_id (ULID), owner_token_hash, created_at
  -- kasowanie po owner_token_hash (RODO)

messages
  id, conversation_id (FK CASCADE), role (user|assistant),
  content            -- user = ZREDAGOWANE pytanie; assistant = złożone przez backend
  normalized_question_hash   -- dedup, tylko user
  product_status     -- answered|abstained|needs_clarification, tylko assistant
  rating, rating_reason_code, created_at

generations
  id, message_id (FK CASCADE), operation_id (UNIQUE — idempotencja),
  model, response_type, input_tokens, output_tokens, cost,
  infra_status       -- completed|invalid_schema|provider_timeout|provider_refusal|corpus_integrity_error|...
  created_at

generation_context
  id, generation_id (FK CASCADE), answer_unit_id, content_hash
  -- co model WIDZIAŁ = podstawa walidacji (∈ context + hash)

message_units
  id, generation_id (FK CASCADE), answer_unit_id,
  validation_status, -- accepted|rejected_unknown_unit|rejected_hash_mismatch|rejected_injection
  display_ordinal    -- kolejność renderu, tylko accepted
~~~
Enumy: **PHP backed string** (`app/Enums/`), zero hardkodu. W migracjach wartości **literalnie** (historyczny kontrakt, nie `Enum::cases()`).

## Korpus (plik, nie tabele)
- **Źródło: `/opt/lampp/htdocs/kings5-docs`** (VitePress, PL). Plik→URL prosto: `start/pojecia.md` → `/start/pojecia`, `index.md` → `/`.
- `chat:build-corpus`: docs → plik JSON jednostek: `answer_unit_id` (stabilny ≠ `content_hash`),
  `content`, `content_hash`, `intents[]`, `canonical_url`.
- Gate: tylko jednostki, które **Ty zatwierdziłeś** (human review = granica anti-injection).
- Rebuild = nowy plik; `content_hash` w `generation_context` chroni interpretację starych logów.

## Retrieval za interfejsem (docs BĘDĄ rosły)
Dziś ~6 stron = **zalążek**; instrukcja urośnie. Dlatego dobór kontekstu idzie przez **cienki interfejs `CandidateRetriever`**:
- **v1 = full-corpus** (cały korpus w promptcie — OK póki mały).
- Gdy urośnie (próg kosztu / lost-in-the-middle) → podmiana na **lexical, potem vector** — **bez ruszania walidatora, AskDocs ani UI**.
- Rdzeń anty-halucynacji (`∈ context` + `content_hash`) jest **niezależny od retrievalu** — rozrost korpusu go nie narusza.

## Przepływ jednego pytania
~~~
1. throttle + budżet OK?           (inaczej: komunikat, bez wywołania AI)
2. operation_id już użyty?         -> zwróć istniejący wynik (idempotencja)
3. zredaguj PII -> zapisz messages(user)
4. zbuduj kontekst z korpusu -> [POZA transakcją] zawołaj OpenRouter
5. walidator: response_type + KAŻDY answer_unit_id ∈ context + content_hash
6. zapisz generations + generation_context + message_units + messages(assistant)
7. render: zatwierdzone jednostki (escaped) + link  |  abstynencja  |  „problem techniczny"
~~~

## Pre-launch (NIE kod v1 — ale PRZED wpuszczeniem klientów)
- [ ] Ręczny check: injection w pytaniu, pytanie poza zakresem, abstynencja — działają.
- [ ] Nota prywatności + usuwanie danych (po `owner_token`).
- [ ] Świadomość: OpenRouter/Anthropic przetwarzają pytania (DPA / umowa).
- [ ] Smoke-test strict JSON na realnym endpoincie przeszedł.

## Odłożone (backlog hardeningu z audytów — gdy ruch urośnie)
`assistant_operations` + lease + crash-recovery · wersjonowany registry korpusu · szyfrowanie raw PII ·
formalny eval-gate + zbiory ludzkie + progi liczbowe · etap monitoringu + alerty · immutable releases /
expand→contract · multi-instancja (shared store) · klasyfikator ML injection · retrieval lexical/vector ·
clarification z kontynuacją · pełna kuratela Filament.

## Środowisko
Praca codzienna: **LAMPP PHP 8.2**. Testy schematu: **Docker MySQL 8.4 + PHP 8.5** (`docker-compose.yml`).
Prod: **MySQL 8.4 + PHP 8.5**.

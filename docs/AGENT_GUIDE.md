# AGENT_GUIDE.md — Jedno źródło prawdy (instrukcja dla agenta AI)

> Cel: agent ma wiedzieć, **który zapis jest wiążący**, co jest nieaktualne i **co poprawić** —
> żeby NIE było dwóch źródeł prawdy. Ten plik to **mapa precedencji + lista poprawek**,
> a nie nowy kontrakt. Po wykonaniu sekcji 2 zaktualizuj/skróć tę sekcję.

## 1. Hierarchia źródeł (od najwyższego)

1. **Kod, który działa** — `app/`, `config/`, `database/migrations/`, `tests/`. Zielone testy = kontrakt.
   W razie sprzeczności **kod wygrywa** z dowolnym dokumentem.
2. **Wiążące docs v1** — `docs/SCOPE_V1.md` + `docs/KICKOFF_V1.md`
   (rdzeń: grounded WYBÓR `{response_type, answer_unit_ids[]}`, walidacja `∈ generation_context` + `content_hash`).
   Provider / routing / bezpieczeństwo: `docs/BIELIK_INTEGRATION.md`.
3. **Schemat bazy** — migracje + `docs/DATABASE_SCHEMA.md`.

**Backlog / SUPERSEDED — NIE są źródłem prawdy:** `docs/AI_ASSISTANT_DESIGN.md`, `docs/ROADMAP.md`
(mają banner). Dolne sekcje `CLAUDE.md` (STACK / MODEL DANYCH / ARCHITEKTURA / ROZMIESZCZENIE)
są **częściowo sprzed pivota** — patrz sekcja 2.

**Zasada rozstrzygania:** gdy dokument przeczy kodowi → dokument jest błędny → **popraw dokument, nie kod**
(chyba że to kod łamie SCOPE_V1 / KICKOFF_V1 — wtedy popraw kod).

## 2. Co poprawić w `CLAUDE.md` (rozbieżności z kodem — stan po integracji Bielika)

- **STACK, punkt „AI":** `config/ai.php` → **`config/askdocs.php`** (`config/ai.php` USUNIĘTY — kolizja z pakietem `laravel/ai`).
  AI to **hybryda: Bielik (lokalny Ollama) primary + OpenRouter fallback**, nie sam OpenRouter. Dla Bielika retriever **lexical** (pełny korpus go przepełnia).
- **KRYTYCZNE PUŁAPKI #4 (prompt caching `cache_control` / `cache_read_input_tokens`):** to mechanizm Anthropic — **NIEAKTUALNE**.
  Architektura = SELECT-only grounded; korpus jest w `system`, ale cachowanie zależy od providera (OpenRouter/Ollama), nie od `cache_control`. Zaktualizować lub usunąć punkt.
- **KRYTYCZNE PUŁAPKI #5 (model id):** model `openai/gpt-5.4-nano` jest OK, ale pochodzi z **`config/askdocs.php`**, nie `config/ai.php`.
- **ROZMIESZCZENIE:** `config/docs.php` **NIE ISTNIEJE**. Faktycznie:
  `config/corpus.php` (base_url docs, ścieżki korpusu), `config/askdocs.php` (providery, routing, retriever, breaker, lease, deadline),
  `config/chat.php` (pepper tokenu, kill-switch, dzienny budżet). Dopisać moduł **`app/AskDocs/`**:
  kontrakty (`ChatModel`, `AnswerUnitSelector`, `EndpointResolver`), adaptery (`OllamaChatModel`, `OpenRouterChatModel`),
  `Selection/FailoverAnswerUnitSelector`, `GroundingValidator`, `CircuitBreaker`, `Adapters/Discovery/DnsEndpointResolver`, `Security/EndpointAllowlist`.
- **MODEL DANYCH — `generations`:** dopisać kolumny rezerwacji (decyzja R):
  `status` (pending/processing/completed/failed), `processing_owner`, `processing_started_at`, `lease_expires_at`,
  `request_fingerprint`, `execution_attempt`, `metadata` (JSON, m.in. `attempts[]`).
  **Tabele:** faktyczny zestaw = migracje (SCOPE_V1: 5 tabel app). `answer_drafts` / `corpus_versions` / `answer_unit_versions`
  z `CLAUDE.md` to szkic — **sprawdź migracje, nie zakładaj**.
- **Kontrakt odpowiedzi:** `{answer, link, covered}` oraz `approved_answers` = **PRZESTARZAŁE**.
  Obowiązuje `{response_type, answer_unit_ids[]}` + render jednostki **verbatim** + link z manifestu (nie od modelu).
- **Strefa czasu:** `config/app.php` = `env('APP_TIMEZONE', 'Europe/Warsaw')` (nie `UTC`).

## 3. Zasady, żeby dwa źródła prawdy nie wróciły

- Po zmianie kodu **aktualizuj `CLAUDE.md` + właściwy doc w tym samym kroku** — nie zostawiaj rozjazdu.
- **Nie twórz** nowych dokumentów dublujących SCOPE_V1 / KICKOFF_V1. Rozszerzenia → do istniejących plików.
- Dokumenty SUPERSEDED zostają tylko jako historia z bannerem — **nie cytuj ich jako wiążących**.
- Nowe pliki PL → czysty UTF-8 (weryfikuj: `grep -cP '[ÃÄÅ]' plik` → 0).

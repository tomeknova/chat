# BIELIK_INTEGRATION.md — roadmap + best practices (lokalny LLM w Laravel 12)

> Cel: wpiąć **Bielik (lokalny Ollama)** jako provider AskDocs — jako **hybrydę**
> (Bielik gdy żyje → OpenRouter w fallbacku), nie zamiennik. Zasada: *simple =
> mniej części, nie mniej bezpieczeństwa* (jak `SCOPE_V1.md`).
> Wiedza operacyjna: `memory/project_bielik_integration`.

## Co już potwierdzone (spike — Faza 0 ✅)
- Ollama **`/v1/chat/completions` + `response_format: json_schema`** działa z kontraktem AskDocs
  (`bielik-11b-v3-q80`): trafia w jednostkę i abstynuje na pytanie spoza docs. Sub-sekunda.
- `usage` ma tokeny, **brak `cost`** (lokalne, darmowe).
- Wniosek: istniejący `AskDocs.callModel()` zadziała po podmianie `base_url`/`model`
  + **warunkowym usunięciu bloku `provider`** (OpenRouter-only).

## Architektura docelowa
```
Chat → AskDocs
        ├─ CandidateRetriever  (Bielik: lexical top-k; OpenRouter: full/lexical)
        ├─ ChatModel (interface)
        │     └─ ModelRouter: probe Bielik PO NAZWIE → OllamaChatModel
        │                         (down) → OpenRouterChatModel
        └─ walidator: answer_unit_id ∈ generation_context (atomowy)  ← niezależny od providera
                 → render zatwierdzonej jednostki (dosłownie) + link
```
Rdzeń anty-halucynacji (`∈ context`) jest **wspólny** dla obu providerów — to klucz: model
tylko *wybiera*, więc słabszy 11B nie zaszkodzi.

## Roadmap (fazy z bramkami — GO przed kodem)

### Faza A — Bielik testowalny przez czat (mały krok, pod „przetestować szukanie")
- `config/ai.php`: `driver` (`openrouter`|`ollama`), per-driver `base_url`/`model`.
- `callModel()`: **nie wysyłaj `provider`**, gdy `driver !== openrouter`; `cost = null` gdy brak w `usage`.
- **Bramka:** flip `.env` na Bielika → realne pytanie w UI → grounded odpowiedź z Bielika.
  Testy `Http::fake` na endpoint Ollama (answer + out_of_scope).

### Faza B — Resolver po NAZWIE (twardy wymóg — DHCP zmienia IP)
- `app/Actions/Bielik/ResolveBielikEndpoint` (NIE `app/Services/` — konwencja §7):
  (1) FQDN z configu (`tomasz-MS-7C37.router.taotronics.com:11434`) + probe `/api/tags`;
  (2) cache **last-known-good IP** (`Cache::put('bielik.endpoint', …, 60s)`) + `BIELIK_IP_FALLBACK`;
  (3) **sweep LAN identyfikujący po MODELU** (`bielik` w `/api/tags`), nie po adresie.
- **Sweep tylko async** (artisan command + scheduler/queue), **nigdy inline** (254×0.15 s ≈ 38 s zabija request).
  Na żądaniu: nazwa+cache fail → natychmiast OpenRouter; sweep aktualizuje cache na przyszłość.
- Probe timeout **~2 s** (user czeka); base_url cachowany (TTL ~30–60 s) — nie probe'ować co wiadomość.
- **Bramka:** symulacja zmiany IP (FQDN/cache) → endpoint nadal się rozwiązuje; test fallbacku.

### Faza C — Hybryda (provider router + fallback)
- `interface ChatModel { complete(array $messages, array $schema): ?ModelResult; }`
  → `OllamaChatModel`, `OpenRouterChatModel`. `ModelRouter` woła resolver z Fazy B.
- Bind w `AppServiceProvider`; **AskDocs zależy od interfejsu**, nie od konkretu (jak `CandidateRetriever`).
- **Bramka:** test routingu (Bielik up → Ollama; down → OpenRouter), `Http::fake` obu.

### Faza D — Kontekst: lexical top-k retriever (mały prompt dla Bielika)
- `LexicalRetriever implements CandidateRetriever` — score po słowach/`intents`, zwraca top-k (np. 8).
- Próg w configu; dla Bielika lexical (mały ctx), dla OpenRouter wedle kosztu.
- **NIE** podbijać `num_ctx` (16,5k full-corpus wolne na RTX 3090).
- **Bramka:** prompt Bielika < ctx; trafność selekcji nie spada (eval Faza E).

### Faza E — Hardening + observability
- `generations.model` już rozróżnia providera (bielik-… vs openai/…); opcjonalnie `latency_ms`.
- Log decyzji routingu (który provider, dlaczego); kill-switch per provider; budżet **tylko** dla płatnego (OpenRouter).
- Eval: zestaw pytań PL → porównanie trafności selekcji Bielik vs OpenRouter; warmup/`keep_alive` Ollamy (cold-start po idle).

## Najlepsze praktyki — asymilacja lokalnego LLM w Laravel 12

1. **Provider za interfejsem (Strategy).** `ChatModel`/`CandidateRetriever` jako kontrakty;
   konkrety (Ollama/OpenRouter) bindowane w `AppServiceProvider`. AskDocs nie wie, kto odpowiada.
2. **Config-driven, zero hardcode.** `config/ai.php` + `.env` per środowisko; typ providera = Enum,
   nie magic string. Hosty po NAZWIE, nie IP.
3. **Structured output = kontrakt niezależny od modelu.** `json_schema` + **walidator backendu**
   (`∈ context`). Provider może być słaby — grounding go pilnuje. To architektoniczny zwornik.
4. **Resilience na request-path.** Krótkie timeouty (user czeka), probe-with-cache (lekki circuit-breaker),
   łańcuch fallbacku, **degradacja zamiast błędu** (Bielik down → OpenRouter; oba down → „problem techniczny").
5. **Wolne operacje → tło.** Sweep LAN, warmup modelu, re-index korpusu = artisan command + scheduler/queue
   (`ShouldQueue`). **Nigdy** w cyklu żądania. Wynik cachowany.
6. **Observability.** Per generacja zapisuj provider/model/tokeny/koszt/latencję (mamy `generations`).
   Loguj decyzje routingu — inaczej „czemu poszło na OpenRouter?" jest nieodgadywalne.
7. **Koszt i bezpieczniki rozdzielone per provider.** Bielik darmowy (`cost=null`, poza budżetem);
   budżet/denial-of-wallet dotyczy płatnego. Kill-switch globalny + per provider.
8. **Prywatność jako atut lokalnego modelu.** Dane nie wychodzą z LAN (Bielik) — ale i tak redaguj PII
   (spójność z fallbackiem OpenRouter). Caveat: mechanizm działa tylko w tym samym LAN.
9. **Testowalność bez sieci.** `Http::fake` dla Ollamy i OpenRoutera; testy routingu (up/down), resolvera
   (FQDN→cache→sweep), walidatora. Zero realnych wywołań w CI.
10. **YAGNI/przyrostowo.** Path A (config swap) najpierw — daje testy od ręki. Resolver/hybryda/lexical
    dokładać, gdy realnie potrzebne. Bez nowych top-level katalogów w `app/` (konwencja §7).

## Ryzyka / decyzje otwarte
- **LAN-only** — chat poza siecią Bielika ⇒ tylko OpenRouter / VPN.
- **q80 vs q6k** — jakość vs szybkość/VRAM (eval rozstrzygnie).
- **Cold-start** — pierwszy call po idle ładuje model; rozważyć `keep_alive` Ollamy lub warmup w schedulerze.
- **Jakość lexical retrievera** — zbyt agresywny top-k może uciąć trafną jednostkę; mierzyć evalem.
- **Brak `cost` z Ollamy** — telemetria kosztu tylko dla OpenRoutera (Bielik = 0).

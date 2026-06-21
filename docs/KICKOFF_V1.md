# KICKOFF v1 — runway na start (następna sesja, na „GO")

> Cel: na „GO" ruszamy od razu, bez ustalania od nowa. Tu jest cały runway kroku 1.
> Zakres v1: `docs/SCOPE_V1.md`. Środowisko: **LAMPP 8.2 wystarcza do smoke** (Docker/MySQL 8.4 dopiero przy schemacie — `memory/reference_docker_test_env`).

## Krok 1 (pierwszy na GO): `chat:assistant-smoke`
**Cel:** udowodnić, że model zwraca **strict JSON** (`response_type` + `answer_unit_ids[]`) end-to-end.
Bez bazy, bez korpusu — **2–3 zaszyte jednostki** (fixture). Jedno małe realne wywołanie.

### Model
- Główny: **`openai/gpt-5.4-nano`** · fallback `mistralai/ministral-14b-2512`. (Porównanie + powody: `memory/project_ai_provider`.)

### Zweryfikowany format żądania OpenRouter (2026-06-21, z docs)
`POST https://openrouter.ai/api/v1/chat/completions`, header `Authorization: Bearer ${OPENROUTER_API_KEY}`.
~~~json
{
  "model": "openai/gpt-5.4-nano",
  "messages": [
    {"role": "system", "content": "<instrukcja + UNTRUSTED korpus 2-3 jednostek>"},
    {"role": "user", "content": "<pytanie>"}
  ],
  "response_format": {
    "type": "json_schema",
    "json_schema": {
      "name": "askdocs_response",
      "strict": true,
      "schema": {
        "type": "object",
        "additionalProperties": false,
        "properties": {
          "response_type": {"type": "string", "enum": ["answer", "clarification", "abstention", "out_of_scope"]},
          "answer_unit_ids": {"type": "array", "items": {"type": "string"}}
        },
        "required": ["response_type", "answer_unit_ids"]
      }
    }
  },
  "provider": {
    "only": ["openai", "azure"],
    "allow_fallbacks": false,
    "require_parameters": true,
    "data_collection": "deny"
  }
}
~~~
- **Płaska schema** (bez `if/then/oneOf`; `anyOf` opcjonalnie później). Cała warunkowość → walidator backendu (kontrakt SCOPE/A-I).
- `response_format.json_schema` = `{ name, strict:true, schema }` — dokładnie te pola, `additionalProperties:false`.
- `provider.only` przypina dostawców z structured outputs; **`require_parameters:true`** gwarantuje wsparcie `response_format`. (GLM wymagałby `only:["AtlasCloud"]`; nano/Ministral — bez problemu.)

### Config do dodania (na GO — to jest kod, więc dopiero po GO)
`config/ai.php`:
~~~php
return [
    'model' => env('AI_MODEL', 'openai/gpt-5.4-nano'),
    'fallback_model' => env('AI_FALLBACK_MODEL', 'mistralai/ministral-14b-2512'),
    'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
    'key' => env('OPENROUTER_API_KEY'),
    'providers' => ['openai', 'azure'], // structured-output-capable
];
~~~
`.env` (klucz i base_url JUŻ SĄ; zmienić tylko model):
~~~
AI_MODEL=openai/gpt-5.4-nano
AI_FALLBACK_MODEL=mistralai/ministral-14b-2512
~~~

### Komenda — kontrakt (implementacja na GO)
`app/Console/Commands/AssistantSmoke.php` → sygnatura `chat:assistant-smoke`:
1. zaszyty mini-korpus: 2–3 jednostki `{answer_unit_id, content}` (np. `start.logowanie`, `start.pulpit`);
2. zbuduj body jak wyżej (system = krótka instrukcja + jednostki; user = pytanie testowe lub `--question=`);
3. `Http::withToken(config('ai.key'))->post(config('ai.base_url').'/chat/completions', $body)`;
4. asercje: HTTP 200 · JSON parsowalny · `response_type` obecny · każdy `answer_unit_id` ∈ zaszyte jednostki;
5. wypisz odpowiedź + `usage` (tokeny/koszt); exit 0 jeśli OK, !=0 jeśli kontrakt złamany.

### Uruchomienie (LAMPP, bez Dockera)
~~~
php artisan chat:assistant-smoke
php artisan chat:assistant-smoke --question="jak się zalogować do panelu?"
~~~

## Po zielonym smoke — kolejność v1 (szczegóły w SCOPE_V1)
2. **Fundament:** ~5 tabel + enumy + modele *(tu wchodzi Docker MySQL 8.4)*.
3. **`chat:build-corpus`:** `/opt/lampp/htdocs/kings5-docs` → plik JSON jednostek (cięcie po nagłówkach H2/H3; `answer_unit_id` stabilny; `canonical_url` z mapy plik→URL).
4. **AskDocs:** `CandidateRetriever` (full-corpus za interfejsem) + klient OpenRouter + walidator (`∈ context` + `content_hash`) → podmiana stuba w `app/Livewire/Chat.php:95`.
5. **Bezpieczniki:** throttle + dzienny budżet + kill-switch; minimalna telemetria/kuracja Filament.
**Pre-launch:** ręczny check injection/abstynencja, nota prywatności + delete po owner_token, smoke strict JSON. → publicznie.

## Drobiazgi do zrobienia przy GO
- ✅ Zaktualizować sekcję „AI" w **CLAUDE.md**: Anthropic SDK → OpenRouter (OpenAI-compatible), model `openai/gpt-5.4-nano`.
- ✅ `.env`: `AI_MODEL` z `anthropic/claude-haiku-4.5` na `openai/gpt-5.4-nano`.

## Status startowy (stan historyczny — 2026-01)
> **Uwaga:** Ten opis był aktualny na start projektu. `chat:build-corpus` istnieje i działa
> (`app/Actions/BuildCorpus.php` + `app/Console/Commands/BuildCorpusCommand.php` wdrożone,
> zintegrowane z kings5-docs). Reszta (enumy, tabele aplikacyjne, klient/walidator, UI AI) —
> wciąż do budowy wg ROADMAP.

Skorupa: frontend + chat **mock** (`Chat.php:95` placeholder) + Filament + auth + domyślne tabele Laravela.
~~**Zero** produktu AI~~ → `BuildCorpus` (Action + komenda) wdrożone i działające.
Brak: `app/Enums`, tabel aplikacyjnych, klienta/walidatora. Wszystko inne budujemy od zera wg ROADMAP.

# Konwencje backendu — Laravel 12 / Filament 5 / Livewire 4

> Zaadaptowane z CLAMS (`docs/ALL_INVARIANTS.md`, `docs/FILAMENT5_CHEATSHEET.md`) i KINGS5
> (`docs/ENUMS_AND_FACTORIES.md`), **przepisane pod TEN projekt** (single-site, single-locale, mały).
> Reguły multi-site/RBAC/membership/money/webhooks z CLAMS — **NIE dotyczą nas, pominięte**.
>
> Czytaj przed pisaniem/zmianą kodu PHP. Sekcja 1 (Enums) i 5 (Filament 5 API) są krytyczne —
> tam mieszkały błędy, które kosztowały najwięcej czasu (hardkod zamiast Enumów, złe API wersji).

---

## 0. Stack + version locks (semantyka wersji ma znaczenie)

- **Laravel 12** (nie 10/11). Struktura `bootstrap/app.php`, brak `app/Http/Kernel.php`.
- **Filament 5** (nie 3/4). Namespace’y i sygnatury — patrz §5.
- **Livewire 4** (nie 3).
- **DB:** MySQL/MariaDB, połączenie `mysql`. PHP 8.2 (local) / 8.5 (prod).

**Źródło wiedzy (zasada nadrzędna — Twój ból „źródła wiedzy"):**
- Konwencje biorę **z tego repo** (CLAUDE.md + `docs/`), nie z pamięci o innych wersjach.
- **Nie pewny API → sprawdź, nie zgaduj:** `composer show <pakiet>`, źródło w `vendor/`, oficjalne docs
  wersji. Nie pisz sygnatury „z głowy", jeśli wersja mogła ją zmienić (patrz §5).
- Sprawy dot. modeli AI/Anthropic/OpenRouter → weryfikuj na żywo (skill `claude-api`), nie z pamięci.

---

## 1. ENUMS — JEDNO ŹRÓDŁO PRAWDY (krytyczne — najdroższy błąd)

**Każda wartość statusu / typu / roli / klasyfikacji = Enum w `app/Enums/`.** Nigdy hardkodowany string.

- **Backed enum `string`.** Wartość case’a = wartość zapisywana w DB.
- **Standardowe metody enuma** (wzorzec KINGS5):
  - `label(): string` — etykieta PL do UI
  - `color(): string` — kolor Filamenta (np. `gray`, `info`, `warning`)
  - `icon(): string` — Heroicon (jeśli używane w UI)
  - `static options(): array` — `value => label` dla Filament `Select`
- **Enum ↔ DB 1:1.** Jeśli kolumna jest typu DB ENUM — wartości muszą się zgadzać; zmiana ENUM w DB i
  zmiana PHP Enuma **w tym samym commicie**. Jeśli kolumna `string` + cast — Enum i tak jest źródłem
  dozwolonych wartości.
- **Cast na modelu:** `protected function casts(): array { return ['status' => MessageRole::class]; }`
- **No magic strings:** każdy string używany w >1 miejscu → Enum lub stała `config`. Porównania typu
  `if ($x === 'active')` są zakazane — `if ($x === Status::ACTIVE)`.
- **Przejścia statusów (jeśli dotyczy):** nie `->update(['status' => X])` na ślepo —
  metoda `canTransitionTo(self $new): bool` na enumie + wyjątek, gdy przejście niedozwolone.

```php
// app/Enums/MessageRole.php
enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Użytkownik',
            self::Assistant => 'Asystent',
        };
    }
}
```

> W TYM projekcie enumy pojawią się przy migracjach: rola wiadomości (`user`/`assistant`),
> ocena (`up`/`down`), itp. — od razu jako Enum, nie string.

---

## 2. Mutacje domenowe → klasa Action

- **Logika biznesowa / mutacje** (INSERT/UPDATE/DELETE, wywołanie AI) → `app/Actions/*Action.php`.
- **Controller / Livewire / Filament Resource = cienkie** — tylko wołają Action, nie zawierają logiki.
  (Zgodne z CLAUDE.md: „cienki controller/Livewire — logika w Action".)
- Przykład docelowy: `AskDocs` (woła OpenRouter, zwraca `{answer, link, covered}`) — komponent
  `Chat` tylko ją wywołuje.

---

## 3. Modele

- **`$fillable` zawsze.** Nigdy `$guarded = []` (modele biorą input z Filament/Livewire/API).
- **Casty** przez `casts()` (Laravel 12), w tym enumy i `'hashed'` dla hasła.
- **Relacje** typowane, eager-load tam gdzie pętla (N+1).
- Bez `Repository` / `DTO` / `CQRS` — Eloquent + Scope/Service wystarcza (§7).

---

## 4. Bezpieczeństwo / kod

- **UTF-8 literal** — polskie znaki (ą ę ś ź ż ó ł ń ć) literalnie w kodzie. Nigdy `\u{...}`. Weryfikuj po zapisie.
- **Autoryzacja przed mutacją** — `Gate::authorize(...)` / Policy, gdy są chronione zasoby. Ukrycie
  buttona w UI ≠ zabezpieczenie. (Dla nas lekkie — panel `/admin` chroniony `canAccessPanel`, §5.)
- **Publiczny endpoint → throttle** (`RateLimiter`) — chroni przed kosztem/abuse (czat).
- **Sekrety tylko w `.env`** (`OPENROUTER_API_KEY`) — nigdy w kodzie/gicie.
- **I/O w tle:** ciężkie/wolne I/O (mail, długie zadania, build korpusu) → `ShouldQueue`.
  Wyjątek świadomy: odpowiedź AI w czacie jest synchroniczna (user czeka na wynik) — to OK.

---

## 5. Filament 5 — API (semantyka wersji — Twój ból „złe API")

**To są realne różnice F5 vs F3/F4 — nie pisz API „z pamięci".**

| ❌ Filament 3 (źle) | ✅ Filament 5 (poprawnie) |
|---|---|
| `Filament\Tables\Actions\*` | **`Filament\Actions\*`** (Action, EditAction, DeleteAction, BulkActionGroup…) |
| `Filament\Forms\Components\Section` (Grid/Tabs/Wizard/Fieldset) | **`Filament\Schemas\Components\Section`** (layout → Schemas) |
| `form(Form $form): Form` | **`form(Schema $schema): Schema`** (`use Filament\Schemas\Schema`) |
| `infolist(Infolist $i): Infolist` | **`infolist(Schema $schema): Schema`** |
| `->mountUsing(fn ($form, $record) => …)` | **`->fillForm(fn (Model $record): array => […])`** |
| `assertFormSet()` / `assertFormFieldExists()` | **`assertSchemaStateSet()` / `assertSchemaComponentExists()`** |

- **Pola formularza** (`TextInput`, `Select`, `Toggle`…) **zostają** w `Filament\Forms\Components\*`.
- **User panelu:** model `User` implementuje `FilamentUser` z `canAccessPanel(Panel $panel): bool`
  (na prod ogranicza dostęp; bez tego na nie-local panel może być 403/otwarty). Na local Filament wpuszcza usera.
- **Typy property w klasach bazowych F5:** `$navigationGroup: string|UnitEnum|null`,
  `$navigationIcon: string|BackedEnum|null`, `$view` jest **non-static** (`protected`).

> Nasze Filament Resources: `Questions`, `ApprovedAnswers` (review/curation). Pisane wg powyższego.

---

## 6. Livewire 4

- **Livewire 4, nie 3.** Komponent: klasa `app/Livewire/Nazwa.php` → `view('livewire.nazwa')`
  (auto-discovery). Szczegóły konwencji komponentu/blade — `docs/FRONTEND_CONVENTIONS.md` §8.
- **Cienki komponent**: stan + widok; logika w Action (§2).
- **Walidacja** atrybutami `#[Validate(...)]`; **throttle** na akcjach publicznych (§4).

---

## 7. Granice architektury (YAGNI — nie rozbudowuj bez bólu)

- **Bez nowych katalogów top-level w `app/`.** Trzymaj się: `Actions/`, `Models/`, `Livewire/`,
  `Filament/`, `Policies/`, `Http/`, `Enums/`, `Console/`. Nowy katalog bazowy = decyzja usera.
- **Bez Repository / DTO-library / CQRS.** Eloquent + Scope/Service. „DTO" = Form Request / array.
- **3 podobne linie ≠ abstrakcja.** Nowa warstwa/resolver wymaga: realnego bólu + kosztu utrzymania.

---

## 8. Verify-first — zanim powiesz „gotowe"

- Nie deklaruj „production-ready" na słowo. Sprawdź realnie: `Schema::getColumnListing(...)`,
  `method_exists(...)`, grep po symbolu w repo, `php artisan test` (jeśli są testy).
- Po zmianie w Filament — odpal smoke (CRUD/panel wstaje). Po zmianie w kodzie PL — UTF-8 check.

---

## 9. Czego NIE robić (zakazy — tu były błędy)

1. ❌ **Hardkodowany string statusu/typu/roli** — zawsze Enum (`app/Enums/`).
2. ❌ **Magic string w >1 miejscu** — Enum / config constant.
3. ❌ **Logika/mutacja w Controller/Livewire/Resource** — przenieś do Action.
4. ❌ **`$guarded = []`** — zawsze `$fillable`.
5. ❌ **API Filamenta „z pamięci"** (F3 namespace) — sprawdź §5 / wersję.
6. ❌ **Polskie znaki jako `\u{...}`** — literalnie, UTF-8 czysty.
7. ❌ **Sekret w kodzie** — tylko `.env`.
8. ❌ **Nowy katalog top-level w `app/` / Repository / DTO-lib** bez bólu (YAGNI).
9. ❌ **„Gotowe" bez weryfikacji** (Schema/method_exists/grep/test).

---

## 10. Checklist — przed commitem backendu

- [ ] Wartości statusów/typów/ról przez Enum (zero hardkodu)?
- [ ] Mutacje/logika w Action, nie w Controller/Livewire/Resource?
- [ ] Model ma `$fillable` (nie `$guarded = []`) + casty (w tym enumy)?
- [ ] Filament: namespace’y/sygnatury F5 (§5), nie F3?
- [ ] Publiczny endpoint z throttle? Sekrety tylko w `.env`?
- [ ] UTF-8 czysty (polskie znaki literalnie)?
- [ ] Bez nowych katalogów top-level w `app/` / bez zbędnych abstrakcji?
- [ ] Zweryfikowane realnie (nie „z głowy") przed „gotowe"?

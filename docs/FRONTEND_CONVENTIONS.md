# Konwencje frontendu — Blade, SCSS, JS, Livewire

> Zaadaptowane z KINGS5 (`docs/TEMPLATE_BUILD_POLICY.md`) i CLAMS (`docs/FRONTEND_CONVENTIONS.md`),
> **przepisane pod TEN projekt**: single-event, single-locale (PL), Bootstrap 5 + SCSS przez Vite.
> Maszyneria multi-event (templates/{key}, theme::, SectionDataResolver, content_blocks,
> {locale} w route) — **NIE dotyczy nas, świadomie pominięta**.
>
> Czytaj przed tworzeniem/zmianą KAŻDEGO pliku frontu. Sekcja 5 (SCSS) i 12 (zakazy) są
> krytyczne — tam mieszkają błędy, które już popełniałem.

---

## 0. Stack i zasada nadrzędna

- **Front publiczny:** Bootstrap 5.3 + SCSS, kompilowane przez **Vite** (`sass`). Wzorzec: KINGS5.
- **Tailwind** zostaje **wyłącznie pod Filament** (panel `/admin`) — nie używamy go na froncie publicznym.
- **Szablony (licencja):** Landia (rama) + EliteAdmin (dymki czatu). Ich źródła SCSS/markup są **źródłem prawdy** — adaptujemy, nie przepisujemy od zera.
- **Livewire 4** (z Filamenta) — interaktywność (czat).
- **Komentarze w kodzie: EN. UI: PL.** Pliki **czysty UTF-8** (polskie znaki literalne).

**Zasada nadrzędna:** zgodność ze standardem Laravela/frameworka i z konwencją szablonu —
**zero cichych obejść**. Każde odstępstwo sygnalizuj i uzasadnij.

---

## 1. Struktura plików frontu (single-event — bez `templates/{key}/`)

```
resources/views/
├── layouts/
│   ├── master.blade.php          # szkielet HTML (head, @vite, @livewireStyles/Scripts)
│   └── layout.blade.php          # @extends master; wstawia header/footer, content = @yield('contentpages')
├── partials/
│   ├── header.blade.php          # nawigacja (statyczna — single-event)
│   └── footer.blade.php
├── pages/
│   └── home.blade.php            # strona = @extends('layouts.layout') + @section('contentpages')
├── sections/
│   └── chat/
│       └── index.blade.php       # sekcja = entry 'index.blade.php' (osadza komponent Livewire)
└── livewire/
    ├── chat.blade.php            # widok komponentu Livewire
    └── partials/
        └── _message.blade.php    # partial reużywany (prefix '_')

resources/scss/                    # kompletne źródła Landii + nasz _chat
├── app.scss                       # entry Vite: @import bootstrap + bootstrap-icons + main
├── main.scss                      # @import _variables, layouts/*, _sections
├── _variables.scss                # zmienne CSS (paleta Landii + helpery z EliteAdmin)
├── _sections.scss                 # @import wszystkich sekcji (w tym sections/_chat)
├── layouts/                       # _general, _header, _footer, _navmenu, _scrolltop...
└── sections/                      # _hero, ..., _chat

resources/js/
├── app.js                         # import bootstrap.js + libki npm (aos/glightbox/swiper/purecounter) + zachowania szablonu
└── bootstrap.js                   # axios + import * as bootstrap -> window.bootstrap

app/Livewire/
└── Chat.php                       # komponent (PascalCase) -> render view('livewire.chat')
```

---

## 2. Nazewnictwo

| Element | Konwencja | Przykład |
|---|---|---|
| Katalogi sekcji | kebab-case | `sections/chat/`, `sections/hero/` |
| Entry sekcji | **zawsze** `index.blade.php` | `sections/chat/index.blade.php` |
| Partiale blade | prefiks `_` | `_message.blade.php` |
| Strony | kebab-case | `home.blade.php` |
| Pliki SCSS (partiale) | prefiks `_` | `_chat.scss`, `_header.scss` |
| Komponent Livewire | PascalCase klasa → kebab blade | `Chat.php` → `livewire/chat.blade.php` |
| Klasy CSS | **konwencja szablonu (BootstrapMade)** — `.block-element` (np. `.message-bubble`, `.chat-input-wrapper`) | NIE wymyślaj własnego schematu |

> **Klasy CSS:** używamy nazw z szablonów (Landia/EliteAdmin). Dla NOWYCH klas trzymaj się
> stylu BootstrapMade (`.komponent-element`, stany jako osobne klasy). Nie narzucaj własnego BEM,
> jeśli koliduje z szablonem.

---

## 3. Komentarz ścieżki — OBOWIĄZKOWY, 1. linia

Każdy plik Blade zaczyna się od komentarza ze ścieżką:

```blade
{{-- resources/views/sections/chat/index.blade.php --}}
```

Nienegocjowalne. Zapobiega pomyłkom plików przy pracy z agentem AI.

---

## 4. SCSS — ŹRÓDŁA i STRUKTURA

- **Źródła szablonu = źródło prawdy.** Kompletny `resources/scss/` Landii kopiujemy z licencji
  i adaptujemy. **NIE przepisujemy partiali Landii od zera** (to był błąd — robienie własnych
  `_header.scss` itd. zamiast użycia gotowych).
- **Entry `app.scss`** (wzorzec KINGS5): `@import "bootstrap/scss/bootstrap"; @import "bootstrap-icons/...";
  @import 'main.scss';` — Bootstrap jest osobnym vendorem (nie w `main.scss`).
- **Nowa sekcja → import w `_sections.scss`** (z resztą sekcji). **NIE doklejaj importów w `main.scss`**
  „żeby nie ruszać plików Landii" — to obejście. Sekcja jest częścią strony → idzie tam, gdzie sekcje.

## 5. SCSS — KOLORY (krytyczne — najczęstszy mój błąd)

- **Kolory WYŁĄCZNIE przez zmienne CSS z `_variables.scss`** (system kolorów BootstrapMade):
  `var(--accent-color)`, `var(--surface-color)`, `var(--background-color)`, `var(--default-color)`,
  `var(--heading-color)`, `var(--contrast-color)`, oraz helpery `var(--muted-color)`, `var(--border-color)`.
- **NIGDY nie hardkoduj hexów** (`#333`, `#fff`...) w partialach. Zmiana palety = zmiana w jednym
  miejscu (`_variables.scss`). Hardkod = łamanie systemu i niespójność.
- **Przyciemnienia/warianty:** `color-mix(in srgb, var(--accent-color), black 15%)` —
  jak w szablonie, nie własne hexy.
- **Helpery z EliteAdmin** dodajemy do **naszego** `_variables.scss`. **NIE importujemy
  `_variables.scss` admina** (ma swój fiolet `--accent-color:#6c5ce7`) — rdzeń palety zostaje Landii (navy).

```scss
// DOBRZE
.message-bubble { background: var(--background-color); color: var(--default-color); }
.message-group.sent .message-bubble { background: var(--accent-color); color: var(--contrast-color); }

// ŹLE — hardkod
.message-bubble { background: #f4f4f4; color: #505050; }
```

### Breakpoints + media queries zagnieżdżone w selektorze

```scss
.hero-text-center h1 {
  font-size: 3.25rem;

  @media (max-width: 768px) { font-size: 2.25rem; }   // 992 / 768 / 576
}
```

---

## 6. JS / `resources/js` — libki przez npm, nie vendor-pliki

- **Biblioteki szablonu instalujemy przez npm** (jak KINGS5), **nie kopiujemy `assets/vendor/*.js`**.
  Mamy: `aos`, `glightbox`, `swiper`, `@srexi/purecounterjs`, `bootstrap`, `bootstrap-icons`.
- **Import w `app.js`** (+ import CSS libek, gdzie trzeba): `import AOS from 'aos'; import 'aos/dist/aos.css';` itd.
- `bootstrap.js`: `axios` + `import * as bootstrap` → `window.bootstrap` (Modal/Dropdown/Collapse).
- Zachowania szablonu (mobile-nav, scrolltop, scrollspy, AOS.init...) = adaptacja `main.js` Landii,
  z null-guardami (strona bez danego elementu nie ma rzucać błędem).
- **Vite entry** rejestrujemy w `vite.config.js` (`input[]`): `resources/scss/app.scss`, `resources/js/app.js`.

---

## 7. Blade — wzorzec sekcji i dekompozycja

**Entry sekcji** (`sections/{name}/index.blade.php`) = wrapper `<section id class="... section">` + treść/komponent.
**Partial** (`_nazwa.blade.php`) = reużywany fragment, dane przekazywane jawnie przez `@include`.

**Dekompozycja — rozbij sekcję na partiale, gdy** (≥1 prawda):
1. więcej niż jeden blok koncepcyjny (header + body + CTA),
2. powtarzalny markup (karta/wiersz/dymek),
3. zagnieżdżone warunki,
4. > ~80 linii markupu,
5. fragment może być nadpisany/zmieniony niezależnie.

**Domyślnie: w razie wątpliwości — rozbij.** Monolityczny plik sekcji to błąd architektury frontu.

**Import klas w Blade:** klasy używane z `::` importuj przez `@use(...)` na górze pliku
(np. `@use('Illuminate\Support\Str')`). **Bez inline FQCN** (`\App\...`) w środku.

---

## 8. Livewire — konwencja (wg KINGS5, wariant single-event)

- **Klasa** `app/Livewire/Nazwa.php` (PascalCase) → **blade** `resources/views/livewire/nazwa.blade.php` (kebab).
  `render()` zwraca `view('livewire.nazwa')`. Auto-discovery Livewire 4 (bez ręcznej rejestracji).
- **Nagłówek klasy:** docblock ze ścieżką + opisem + `@see` blade. Sekcje banerowe
  (`// === PROPERTIES ===`, `LIFECYCLE`, `ACTIONS`, `RENDER`). Typowane `public` properties.
- **Cienki komponent — logika w Action.** Komponent orkiestruje stan + widok; właściwa logika
  (np. wywołanie AI) idzie do `app/Actions/` (zgodnie z CLAUDE.md).
- **Publiczny endpoint → throttle** (`RateLimiter`) + walidacja (`#[Validate]`).
- **Reużywany fragment** (dymek) → `livewire/partials/_message.blade.php`, dane przez `@include`.
- **Komentarz ścieżki** w 1. linii blade'a Livewire też obowiązuje.
- **Blade ma JEDEN root element** (wymóg Livewire).

---

## 9. Linki, obrazy, assety

- **Linki wewnętrzne: `route()`** (single-locale — **bez** parametru `locale`, np. `route('home')`,
  `url('/')`). Nie hardkoduj ścieżek typu `/admin/...` w treści.
- **Obrazy z uploadu: `Storage::url(...)`** (nie surowa ścieżka — 404 na prodzie). `alt` + `loading="lazy"`.
- **Assety statyczne: `@vite([...])`** w `master.blade.php`.
- **`public/build` i `public/hot` są gitignorowane** — serwer buduje przez `npm run build` (DEPLOY.md).
  Nie commituj artefaktów buildu.

---

## 10. Formularze (publiczne)

- Czat publiczny = **Livewire** (`wire:submit`, `wire:model`, `@error`, throttle w komponencie) —
  nie klasyczny `<form>` POST.
- Panel admina (review) = **Filament** — obsługuje swoje formularze sam, nie piszemy ich ręcznie.
- Klasyczny `<form>` (gdyby zaszedł): `@csrf`, `old()`, `@error()`, throttle na route, honeypot.

---

## 11. Czego NIE robić (zakazy — tu mieszkają błędy)

1. ❌ **Hardkodowane kolory (hex)** w SCSS — zawsze `var(--...)` z `_variables.scss`.
2. ❌ **Przepisywanie partiali szablonu od zera** — używaj źródeł Landii/EliteAdmin.
3. ❌ **Doklejanie importów w `main.scss`** zamiast w `_sections.scss` (obejście).
4. ❌ **Kopiowanie vendor-JS z szablonu** zamiast instalacji przez npm.
5. ❌ **Importowanie `_variables.scss` admina** (nadpisałby paletę Landii).
6. ❌ **Logika biznesowa / zapytania DB w Blade** — Action/komponent dostarcza dane.
7. ❌ **Inline FQCN** (`\App\...`) — importuj przez `@use()`.
8. ❌ **Brak komentarza ścieżki** w 1. linii blade'a.
9. ❌ **Inline-styles do layoutu** — od tego jest SCSS.
10. ❌ **Polskie znaki bez weryfikacji UTF-8** — sprawdź po zapisie.
11. ❌ **Ciche obejścia standardu** — sygnalizuj i uzasadniaj odstępstwa.

---

## 12. Checklist — przed commitem frontu

- [ ] Komentarz ścieżki w 1. linii każdego blade'a?
- [ ] Kolory w SCSS wyłącznie przez `var(--...)` (zero hardkodu hex)?
- [ ] Nowa sekcja zaimportowana w `_sections.scss` (nie w `main.scss`)?
- [ ] Partiale szablonu z licencji (nie przepisane ręcznie)?
- [ ] Libki JS przez npm + import w `app.js` (nie vendor-pliki)?
- [ ] Powtarzalny markup wydzielony do partiala (`_` prefix)?
- [ ] Entry sekcji to `index.blade.php`?
- [ ] Komponent Livewire: cienki, logika w Action, throttle + walidacja?
- [ ] Klasy importowane przez `@use()` (bez inline FQCN)?
- [ ] Linki przez `route()`/`url()`; obrazy uploadu przez `Storage::url()`?
- [ ] Komentarze EN, UI PL, UTF-8 czysty?
- [ ] `public/build`/`public/hot` poza commitem?

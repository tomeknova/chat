{{-- resources/views/partials/header.blade.php — BootstrapMade (Landia) semantics --}}
<header id="header" class="header d-flex align-items-center sticky-top">
    <div class="header-container container position-relative d-flex align-items-center justify-content-between">

        <a href="{{ url('/') }}" class="logo d-flex align-items-center me-auto me-xl-0">
            <h1 class="sitename">KINGS Docs</h1>
            <span class="sitename-suffix">Asystent AI</span>
        </a>

        <nav id="navmenu" class="navmenu">
            <ul>
                <li><a href="{{ url('/') }}#hero" class="active">Start</a></li>
                <li><a href="{{ url('/') }}#chat">Zapytaj</a></li>
            </ul>
            <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
        </nav>

        {{-- Link back to the docs (per project templates note) --}}
        <a class="btn-getstarted" href="#" target="_blank" rel="noopener">← Dokumentacja</a>

    </div>
</header>

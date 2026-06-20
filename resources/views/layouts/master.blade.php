{{-- resources/views/layouts/master.blade.php — HTML skeleton (mirrors KINGS5) --}}
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'KINGS Docs — Asystent AI')</title>

    {{-- Meta --}}
    <meta name="description" content="@yield('meta_description', 'Pomocnik AI do dokumentacji panelu KINGS.')">
    <meta name="keywords" content="@yield('meta_keywords', 'KINGS, dokumentacja, asystent AI')">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Fonts (Google) --}}
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Montserrat:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Rubik:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    {{-- Livewire --}}
    @livewireStyles

    {{-- Vite: Bootstrap + SCSS --}}
    @vite(['resources/scss/app.scss'])
</head>

<body class="@yield('body_class', 'index-page')">

    {{-- HEADER --}}
    @yield('header')

    {{-- CONTENT --}}
    <main class="main">
        @yield('content')
    </main>

    {{-- FOOTER --}}
    @yield('footer')

    {{-- Optional per-page tail (scripts, modals...) --}}
    @yield('after-main')

    {{-- Scroll top --}}
    <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center">
        <i class="bi bi-arrow-up-short"></i>
    </a>

    {{-- Vite: app JS (Bootstrap bundle + UI behaviors) --}}
    @vite(['resources/js/app.js'])
    @stack('scripts')
    @livewireScripts
</body>

</html>

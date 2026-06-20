{{-- resources/views/pages/home.blade.php — first page --}}
@extends('layouts.layout')

@section('title', 'KINGS Docs — Asystent AI')

@section('contentpages')

    {{-- HERO --}}
    <section id="hero" class="hero section">
        <div class="container">
            <div class="hero-wrapper text-center">
                <span class="hero-badge">Dokumentacja KINGS</span>
                <h1>Zapytaj asystenta AI o panel KINGS</h1>
                <p class="lead">
                    Odpowiada wyłącznie na podstawie dokumentacji i wskazuje link do właściwej
                    strony instrukcji. Bez zgadywania — gdy czegoś nie ma w docs, powie wprost.
                </p>
                <div class="hero-buttons">
                    <a href="#chat" class="btn-get-started">Zadaj pytanie</a>
                </div>
            </div>
        </div>
    </section>

    {{-- CHAT --}}
    @include('sections.chat.index')

@endsection

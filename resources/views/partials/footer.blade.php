{{-- resources/views/partials/footer.blade.php — BootstrapMade (Landia) semantics --}}
<footer id="footer" class="footer">

    <div class="container footer-top">
        <div class="row gy-4">
            <div class="col-lg-6 footer-info">
                <a href="{{ url('/') }}" class="logo d-flex align-items-center mb-3">
                    <span class="sitename">KINGS Docs — Asystent AI</span>
                </a>
                <p>Odpowiedzi wyłącznie na podstawie dokumentacji panelu KINGS, z linkiem do właściwej strony instrukcji.</p>
            </div>

            <div class="col-lg-3 col-md-6 footer-links">
                <h4>Nawigacja</h4>
                <ul>
                    <li><a href="{{ url('/') }}#hero">Start</a></li>
                    <li><a href="{{ url('/') }}#chat">Zapytaj asystenta</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="container copyright text-center mt-4">
        <p>© <span>{{ date('Y') }}</span> <strong class="px-1 sitename">KINGS Docs</strong></p>
    </div>

</footer>

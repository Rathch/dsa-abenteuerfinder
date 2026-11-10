<footer class="site-footer">
    <div class="site-container flex flex-col gap-4 py-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <nav aria-label="Rechtliches und Hilfe">
                <ul class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                    <li><a href="{{ route('impressum') }}" class="text-link" @if (request()->routeIs('impressum')) aria-current="page" @endif>Impressum</a></li>
                    <li><a href="{{ route('datenschutz') }}" class="text-link" @if (request()->routeIs('datenschutz')) aria-current="page" @endif>Datenschutz</a></li>
                    <li>
                        <a href="{{ config('app.elf_url') }}" class="text-link" target="_blank" rel="noopener noreferrer">
                            ELF (Ulisses)<span class="sr-only"> (öffnet in neuem Tab)</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ config('app.gpl_url') }}" class="text-link" target="_blank" rel="noopener noreferrer">
                            GNU GPLv3<span class="sr-only"> (öffnet in neuem Tab)</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ config('app.buymeacoffee_url') }}" class="text-link" target="_blank" rel="noopener noreferrer">
                            Buy Me a Coffee<span class="sr-only"> (öffnet in neuem Tab)</span>
                        </a>
                    </li>
                </ul>
            </nav>
            <a href="{{ config('app.bug_report_url') }}" class="btn btn-secondary btn-sm" target="_blank" rel="noopener noreferrer">
                Fehler melden<span class="sr-only"> (öffnet in neuem Tab)</span>
            </a>
        </div>

        <div class="fan-notice">
            <img
                src="{{ asset('images/fan-produkt-logo.png') }}"
                alt="Fanprodukt zum Rollenspiel Das Schwarze Auge"
                width="220"
                height="62"
                loading="lazy"
                decoding="async"
            >
            <p>
                DAS SCHWARZE AUGE, AVENTURIEN, DERE, MYRANOR, THARUN, UTHURIA und RIESLAND sind eingetragene Marken der Significant Fantasy Medienrechte GbR.
                Ohne vorherige schriftliche Genehmigung der Ulisses Medien und Spiel Distribution GmbH ist eine Verwendung der genannten Markenzeichen nicht gestattet.
            </p>
        </div>
    </div>
</footer>

{{--
SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich

SPDX-License-Identifier: GPL-3.0-only
--}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Verwaltung' }} – DSA-Abenteuerfinder</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    <script>
        (function () {
            try {
                var stored = localStorage.getItem('theme');
                var theme = stored === 'light' || stored === 'dark'
                    ? stored
                    : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.classList.toggle('dark', theme === 'dark');
                document.documentElement.dataset.theme = theme;
                document.documentElement.style.colorScheme = theme;
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="admin-area min-h-screen flex flex-col">
    <a href="#main-content" class="skip-link">Zum Hauptinhalt springen</a>

    <header class="site-header">
        <div class="site-container flex flex-wrap items-center justify-between gap-4 py-4">
            <a href="{{ route('home') }}" class="site-brand">
                <span aria-hidden="true" class="site-brand-mark">@include('partials.logo-eye')</span>
                <span>DSA-Abenteuerfinder</span>
            </a>

            <button
                type="button"
                class="theme-toggle"
                data-theme-toggle
                role="switch"
                aria-checked="false"
                aria-label="Dunkles Design"
            >
                <span class="theme-toggle-track" aria-hidden="true">
                    <span class="theme-toggle-thumb">
                        <svg class="theme-toggle-icon theme-toggle-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="4" />
                            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
                        </svg>
                        <svg class="theme-toggle-icon theme-toggle-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                        </svg>
                    </span>
                </span>
                <span class="theme-toggle-label">Dunkles Design</span>
            </button>
        </div>
    </header>

    <main id="main-content" tabindex="-1" class="site-container flex-1 w-full py-8 sm:py-12">
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-muted">Verwaltung · nicht öffentlich verlinkt</p>
                <form method="post" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">Abmelden</button>
                </form>
            </div>

            {{ $slot }}
        </div>
    </main>

    <footer class="site-footer">
        <div class="site-container py-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-muted">
                Inoffizielles, nicht kommerzielles Fanprojekt. DAS SCHWARZE AUGE, AVENTURIEN, DERE, MYRANOR, THARUN, UTHURIA, RIESLAND und THE DARK EYE sind eingetragene Marken der Ulisses Spiele GmbH, Waldems.
            </p>
            <nav aria-label="Rechtliches">
                <ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <li><a href="{{ route('impressum') }}" class="text-link">Impressum</a></li>
                    <li><a href="{{ route('datenschutz') }}" class="text-link">Datenschutz</a></li>
                </ul>
            </nav>
        </div>
    </footer>

    @livewireScripts
</body>
</html>

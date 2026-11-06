{{--
SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich

SPDX-License-Identifier: GPL-3.0-only
--}}
@if ($paginator->hasPages())
    <nav aria-label="Seitennavigation" class="flex flex-wrap items-center gap-3 pt-2">
        @if ($paginator->onFirstPage())
            <span class="text-muted">Zurück</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="text-link" rel="prev" wire:navigate>Zurück</a>
        @endif

        <p class="text-sm text-muted">
            Seite {{ $paginator->currentPage() }} von {{ $paginator->lastPage() }}
        </p>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="text-link" rel="next" wire:navigate>Weiter</a>
        @else
            <span class="text-muted">Weiter</span>
        @endif
    </nav>
@endif

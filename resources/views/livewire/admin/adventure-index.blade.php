{{--
SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich

SPDX-License-Identifier: GPL-3.0-only
--}}
<div class="space-y-6">
    <div class="space-y-2">
        <h1>Abenteuer</h1>
        <p class="text-muted">Links und Datensätze korrigieren. Gespeicherte Korrekturen bleiben beim nächsten Import erhalten.</p>
    </div>

    <form wire:submit.prevent class="card space-y-4" role="search" aria-label="Abenteuer filtern">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <label for="admin-search" class="block font-semibold">Titel suchen</label>
                <input
                    id="admin-search"
                    type="search"
                    class="field-input"
                    wire:model.live.debounce.300ms="search"
                    autocomplete="off"
                >
            </div>
            <div class="space-y-2">
                <label for="admin-link-filter" class="block font-semibold">Filter</label>
                <select id="admin-link-filter" class="field-input" wire:model.live="linkFilter">
                    <option value="">Alle</option>
                    <option value="corrected">Mit Korrektur</option>
                    <option value="incomplete">Unvollständig</option>
                    <option value="missing">Fehlt im Wiki</option>
                </select>
            </div>
        </div>
    </form>

    <div class="card space-y-4">
        @if ($adventures->isEmpty())
            <p class="text-muted">Keine Abenteuer für diesen Filter.</p>
        @else
            <ul class="divide-y divide-[color:var(--border)]" aria-label="Abenteuerliste">
                @foreach ($adventures as $adventure)
                    <li class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                        <div class="min-w-0 space-y-1">
                            <a href="{{ route('admin.adventures.edit', $adventure['slug']) }}" class="text-link font-semibold">
                                {{ $adventure['title'] }}
                            </a>
                            <p class="text-sm text-muted break-all">
                                Wiki:
                                <a href="{{ $adventure['wiki_url'] }}" class="text-link" target="_blank" rel="noopener noreferrer">
                                    {{ $adventure['wiki_url'] }}<span class="sr-only"> (öffnet in neuem Tab)</span>
                                </a>
                            </p>
                            <ul class="flex flex-wrap gap-2" aria-label="Kennzeichen">
                                @if ($adventure['has_correction'])
                                    <li class="pill">Korrektur</li>
                                @endif
                                @if ($adventure['incomplete'])
                                    <li class="pill">Unvollständig</li>
                                @endif
                                @if ($adventure['missing_from_wiki'])
                                    <li class="pill">Fehlt im Wiki</li>
                                @endif
                            </ul>
                        </div>
                        <a href="{{ route('admin.adventures.edit', $adventure['slug']) }}" class="btn btn-secondary btn-sm">
                            Bearbeiten
                        </a>
                    </li>
                @endforeach
            </ul>

            <div>
                {{ $adventures->links('pagination.admin') }}
            </div>
        @endif
    </div>
</div>

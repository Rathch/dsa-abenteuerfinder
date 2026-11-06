{{--
SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich

SPDX-License-Identifier: GPL-3.0-only
--}}
<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="space-y-2">
            <h1>{{ $title !== '' ? $title : 'Abenteuer bearbeiten' }}</h1>
            <p class="text-muted">Slug: <code>{{ $slug }}</code>
                @if ($hasCorrection)
                    · <span class="pill">Korrektur gespeichert</span>
                @endif
            </p>
        </div>
        <a href="{{ route('admin.adventures.index') }}" class="btn btn-secondary btn-sm">Zur Liste</a>
    </div>

    @if ($saved)
        <div class="callout" role="status">Gespeichert. Die öffentliche Ansicht ist aktualisiert; der nächste Import überschreibt diese Korrektur nicht.</div>
    @endif

    <form wire:submit="save" class="card space-y-8" novalidate>
        <fieldset class="min-w-0 space-y-4">
            <legend class="ink-rule mb-1 font-serif text-xl font-bold">Links</legend>

            <div class="space-y-2">
                <label for="wiki_url" class="block font-semibold">Wiki-URL</label>
                <input id="wiki_url" type="url" class="field-input" wire:model="wiki_url" required @error('wiki_url') aria-invalid="true" aria-describedby="wiki_url-error" @enderror>
                @error('wiki_url')
                    <p class="error-text" id="wiki_url-error"><span aria-hidden="true">⚠ </span>Fehler: {{ $message }}</p>
                @enderror
            </div>
        </fieldset>

        <fieldset class="min-w-0 space-y-4">
            <legend class="ink-rule mb-1 font-serif text-xl font-bold">Stammdaten</legend>

            <div class="space-y-2">
                <label for="title" class="block font-semibold">Titel</label>
                <input id="title" type="text" class="field-input" wire:model="title" required @error('title') aria-invalid="true" aria-describedby="title-error" @enderror>
                @error('title')
                    <p class="error-text" id="title-error"><span aria-hidden="true">⚠ </span>Fehler: {{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <label for="series" class="block font-semibold">Reihe</label>
                    <select id="series" class="field-input" wire:model="series" required>
                        @foreach ($seriesOptions as $option)
                            <option value="{{ $option['slug'] }}">{{ $option['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-2">
                    <label for="experience_level" class="block font-semibold">Erfahrung</label>
                    <select id="experience_level" class="field-input" wire:model="experience_level" required>
                        @foreach ($experienceLevels as $level)
                            <option value="{{ $level->value }}">{{ $level->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-2">
                    <label for="page_count" class="block font-semibold">Seiten</label>
                    <input id="page_count" type="number" min="1" class="field-input" wire:model="page_count">
                </div>
                <div class="space-y-2">
                    <label for="duration_category" class="block font-semibold">Dauer</label>
                    <select id="duration_category" class="field-input" wire:model="duration_category" required>
                        @foreach ($durationCategories as $duration)
                            <option value="{{ $duration->value }}">{{ $duration->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-2">
                    <label for="rule_version" class="block font-semibold">Regeln</label>
                    <select id="rule_version" class="field-input" wire:model="rule_version" required>
                        @foreach ($ruleVersions as $version)
                            <option value="{{ $version->value }}">{{ $version->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-2">
                    <label for="complexity" class="block font-semibold">Komplexität</label>
                    <select id="complexity" class="field-input" wire:model="complexity" required>
                        @foreach ($complexities as $item)
                            <option value="{{ $item->value }}">{{ ucfirst($item->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-2">
                    <label for="group_size_min" class="block font-semibold">Gruppengröße min.</label>
                    <input id="group_size_min" type="number" min="1" max="20" class="field-input" wire:model="group_size_min">
                </div>
                <div class="space-y-2">
                    <label for="group_size_max" class="block font-semibold">Gruppengröße max.</label>
                    <input id="group_size_max" type="number" min="1" max="20" class="field-input" wire:model="group_size_max">
                </div>
                <div class="space-y-2">
                    <label for="location_type" class="block font-semibold">Ortsart</label>
                    <select id="location_type" class="field-input" wire:model="location_type" required>
                        @foreach ($locationTypes as $type)
                            <option value="{{ $type->value }}">
                                {{ match ($type) {
                                    \App\Domain\Matching\LocationType::Point => 'Punkt',
                                    \App\Domain\Matching\LocationType::Corridor => 'Korridor',
                                    \App\Domain\Matching\LocationType::Traveling => 'Reise',
                                } }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-2">
                    <label for="primary_location" class="block font-semibold">Ort</label>
                    <select id="primary_location" class="field-input" wire:model="primary_location">
                        <option value="">—</option>
                        @foreach ($placeOptions as $option)
                            <option value="{{ $option['slug'] }}">{{ $option['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-2">
                    <label for="primary_region" class="block font-semibold">Hauptregion</label>
                    <select id="primary_region" class="field-input" wire:model="primary_region">
                        <option value="">—</option>
                        @foreach ($regionOptions as $option)
                            <option value="{{ $option['slug'] }}">{{ $option['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <label class="choice">
                <input type="checkbox" wire:model="exotic">
                <span><span class="choice-label">Exotisch</span></span>
            </label>

            <label class="choice">
                <input type="checkbox" wire:model="incomplete">
                <span><span class="choice-label">Unvollständig</span></span>
            </label>

            <label class="choice">
                <input type="checkbox" wire:model="missing_from_wiki">
                <span><span class="choice-label">Fehlt im Wiki</span></span>
            </label>
        </fieldset>

        <fieldset class="min-w-0 space-y-4">
            <legend class="ink-rule mb-1 font-serif text-xl font-bold">Weitere Regionen</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($regionOptions as $option)
                    <label class="choice" wire:key="region-{{ $option['slug'] }}">
                        <input type="checkbox" value="{{ $option['slug'] }}" wire:model="regions">
                        <span><span class="choice-label">{{ $option['name'] }}</span></span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="min-w-0 space-y-4">
            <legend class="ink-rule mb-1 font-serif text-xl font-bold">Genres</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($genreOptions as $option)
                    <label class="choice" wire:key="genre-{{ $option['slug'] }}">
                        <input type="checkbox" value="{{ $option['slug'] }}" wire:model="genres">
                        <span><span class="choice-label">{{ $option['name'] }}</span></span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="min-w-0 space-y-4">
            <legend class="ink-rule mb-1 font-serif text-xl font-bold">Texte</legend>

            <div class="space-y-2">
                <label for="short_description" class="block font-semibold">Kurzbeschreibung</label>
                <textarea id="short_description" class="field-input min-h-32" rows="5" wire:model="short_description" required @error('short_description') aria-invalid="true" aria-describedby="short_description-error" @enderror></textarea>
                @error('short_description')
                    <p class="error-text" id="short_description-error"><span aria-hidden="true">⚠ </span>Fehler: {{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-2">
                <label for="source_note" class="block font-semibold">Quellennotiz</label>
                <input id="source_note" type="text" class="field-input" wire:model="source_note">
            </div>
        </fieldset>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Speichern</button>
            <a href="{{ route('admin.adventures.index') }}" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>

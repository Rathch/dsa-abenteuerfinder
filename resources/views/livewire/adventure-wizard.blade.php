<div>
    @if ($showResults)
        {{-- ===================== Ergebnisse ===================== --}}
        <section aria-labelledby="results-heading" class="space-y-6">
            <div class="space-y-2">
                <h2 id="results-heading" tabindex="-1">Deine Empfehlungen</h2>
                <p class="text-muted">
                    Diese Abenteuer passen am besten zu euren Antworten. Unter „Warum passt es?“ steht jeweils, woran das liegt.
                </p>
            </div>

            @if (count($fallbackNotes) > 0)
                <div class="callout" aria-labelledby="fallback-heading" role="group">
                    <h3 id="fallback-heading" class="text-lg">Hinweis zur Auswahl</h3>
                    <ul class="mt-2 list-disc space-y-1 pl-6">
                        @foreach ($fallbackNotes as $note)
                            <li>{{ $note }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (count($cards) === 0)
                <div class="card">
                    <p>Zu euren Antworten wurde leider kein Abenteuer gefunden. Ändert einzelne Antworten und versucht es noch einmal.</p>
                </div>
            @else
                <ol class="space-y-6" aria-label="Empfohlene Abenteuer">
                    @foreach ($cards as $card)
                        <li wire:key="card-{{ $card['id'] }}">
                            <article class="card space-y-4" aria-labelledby="adventure-{{ $card['id'] }}-title" data-testid="recommendation">
                                <header class="space-y-1">
                                    <h3 id="adventure-{{ $card['id'] }}-title">{{ $card['title'] }}</h3>
                                    <p class="text-muted">Reihe: {{ $card['series'] }}</p>
                                </header>

                                <dl class="grid gap-x-8 gap-y-3 text-base sm:grid-cols-2">
                                    <div>
                                        <dt class="meta-label">
                                            @include('partials.meta-icon', ['icon' => 'map'])
                                            <span>Schauplatz</span>
                                        </dt>
                                        <dd>{{ $card['location'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="meta-label">
                                            @include('partials.meta-icon', ['icon' => 'hourglass'])
                                            <span>Dauer</span>
                                        </dt>
                                        <dd>{{ $card['duration'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-semibold text-muted">Regeln</dt>
                                        <dd>
                                            @if ($card['edition'] !== null)
                                                <span class="edition-meta">
                                                    @include('partials.edition-eye', ['edition' => $card['edition']])
                                                    <span>{{ $card['rules'] }}</span>
                                                </span>
                                            @else
                                                {{ $card['rules'] }}
                                            @endif
                                        </dd>
                                    </div>
                                    @if ($card['group'] !== null)
                                        <div>
                                            <dt class="text-sm font-semibold text-muted">Gruppengröße</dt>
                                            <dd>{{ $card['group'] }}</dd>
                                        </div>
                                    @endif
                                </dl>

                                @if (count($card['genres']) > 0)
                                    <ul class="flex flex-wrap gap-2" aria-label="Genres">
                                        @foreach ($card['genres'] as $genre)
                                            <li class="pill">{{ $genre }}</li>
                                        @endforeach
                                    </ul>
                                @endif

                                <p>{{ $card['description'] }}</p>

                                <div>
                                    <h4 class="meta-heading">
                                        @include('partials.meta-icon', ['icon' => 'bulb'])
                                        <span>Warum passt es?</span>
                                    </h4>
                                    <ul class="mt-1 list-disc space-y-1 pl-6">
                                        @foreach ($card['reasons'] as $reason)
                                            <li>{{ $reason }}</li>
                                        @endforeach
                                    </ul>
                                </div>

                                <ul class="flex flex-wrap gap-3 pt-1" aria-label="Weiterführende Links zu {{ $card['title'] }}">
                                    <li>
                                        <a href="{{ $card['wikiUrl'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">
                                            <span>Im Wiki Aventurica lesen<span class="sr-only"> (öffnet in neuem Tab)</span></span>
                                            @include('partials.external-icon')
                                        </a>
                                    </li>
                                </ul>
                            </article>
                        </li>
                    @endforeach
                </ol>
            @endif

            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="btn btn-secondary" wire:click="editAnswers">Antworten ändern</button>
                <button type="button" class="btn btn-primary" wire:click="restart">Neu starten</button>
            </div>
        </section>
    @else
        {{-- ===================== Progressive Reveal ===================== --}}
        @php
            $errorAnchors = [
                'placeQuery' => 'place-input',
                'regionSlug' => 'regionSlug-0',
            ];
            $current = $fieldsByKey->get($focusField);
        @endphp

        <div class="card space-y-8" aria-labelledby="form-heading">
            <div class="space-y-3">
                <h2 id="form-heading" tabindex="-1">Deine Wünsche</h2>
                <p class="text-muted">
                    Eine Frage nach der anderen. Mit jeder Antwort schränken sich die weiteren Möglichkeiten ein.
                </p>

                <div
                    class="progress-track"
                    role="progressbar"
                    aria-valuemin="0"
                    aria-valuemax="{{ $progress['total'] }}"
                    aria-valuenow="{{ $progress['current'] }}"
                    aria-label="Fortschritt der Angaben"
                >
                    <div class="progress-bar" style="width: {{ round($progress['ratio'] * 100) }}%"></div>
                </div>
            </div>

            @if (count($summaryItems) > 0)
                <div class="space-y-2" aria-label="Bisherige Antworten">
                    <p class="text-sm font-semibold text-muted">Bisher gewählt</p>
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($summaryItems as $item)
                            <li>
                                <button
                                    type="button"
                                    class="pill"
                                    wire:click="editField('{{ $item['field'] }}')"
                                    aria-label="{{ $item['label'] }}: {{ $item['value'] }} – ändern"
                                >
                                    <span class="font-semibold">{{ $item['label'] }}:</span>
                                    {{ $item['value'] }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <p id="focus-status" class="sr-only" role="status" aria-live="polite">
                @if ($focusField === 'review')
                    Alle Angaben erfasst. Du kannst Empfehlungen anzeigen.
                @else
                    Nächste Frage.
                @endif
            </p>

            @if ($errors->any())
                <div id="error-summary" role="alert" tabindex="-1" class="callout callout-error" aria-labelledby="error-summary-heading">
                    <h3 id="error-summary-heading" class="text-lg">Bitte prüfe deine Angaben</h3>
                    <ul class="mt-2 list-disc space-y-1 pl-6">
                        @foreach ($errors->messages() as $key => $messages)
                            @php
                                $anchor = $errorAnchors[$key] ?? (str_starts_with($key, 'genres') ? 'genres-0' : $key.'-0');
                            @endphp
                            <li><a href="#{{ $anchor }}" class="text-link">{{ $messages[0] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($focusField === 'review')
                <section class="space-y-6" aria-labelledby="review-heading">
                    <div class="space-y-2">
                        <h3 id="review-heading" tabindex="-1" class="font-serif text-xl font-bold">Passt das so?</h3>
                        <p class="text-muted">Prüfe deine Angaben. Über „Bisher gewählt“ kannst du einzelne Antworten ändern.</p>
                    </div>

                    <form wire:submit="submit" class="flex flex-wrap items-center gap-3">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="submit">
                            Empfehlungen anzeigen
                        </button>
                        <button type="button" class="btn btn-secondary" wire:click="restart">Neu starten</button>
                    </form>
                </section>
            @elseif ($focusField === 'region')
                <fieldset class="min-w-0 space-y-4" aria-describedby="regionSlug-hint">
                    <legend id="region-legend" tabindex="-1" class="ink-rule mb-1 font-serif text-xl font-bold">
                        In welcher Region soll das Abenteuer spielen?
                    </legend>
                    <p id="regionSlug-hint" class="text-muted">Mit „Egal“ schlagen wir Abenteuer aus allen Regionen vor. Danach kannst du optional einen Ort wählen.</p>
                    @error('regionSlug')
                        <p class="error-text" id="regionSlug-error"><span aria-hidden="true">⚠ </span>Fehler: {{ $message }}</p>
                    @enderror

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($regionOptions as $index => $option)
                            <label class="choice" wire:key="region-{{ $option['value'] }}">
                                <input
                                    type="radio"
                                    id="regionSlug-{{ $index }}"
                                    name="regionSlug"
                                    value="{{ $option['value'] }}"
                                    wire:model.live="regionSlug"
                                    @error('regionSlug') aria-invalid="true" aria-describedby="regionSlug-error" @enderror
                                >
                                <span><span class="choice-label">{{ $option['label'] }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                @if ($regionSlug !== '')
                    @php
                        $suggestions = $this->suggestions;
                        $hasSuggestions = count($suggestions) > 0;
                    @endphp
                    <div
                        class="relative space-y-2"
                        x-data="{
                            active: -1,
                            options() { return Array.from($refs.listbox ? $refs.listbox.querySelectorAll('[role=option]') : []); },
                            mark() {
                                this.options().forEach((el, i) => {
                                    el.setAttribute('aria-selected', i === this.active ? 'true' : 'false');
                                    el.dataset.active = i === this.active ? 'true' : 'false';
                                });
                                const current = this.options()[this.active];
                                if (current) { current.scrollIntoView({ block: 'nearest' }); }
                            },
                            move(step) {
                                const count = this.options().length;
                                if (count === 0) { return; }
                                this.active = this.active < 0
                                    ? (step > 0 ? 0 : count - 1)
                                    : (this.active + step + count) % count;
                                this.mark();
                            },
                            choose(event) {
                                const current = this.options()[this.active];
                                if (current) { event.preventDefault(); current.click(); }
                            },
                            close(event) {
                                if (this.options().length > 0) { event.preventDefault(); $wire.set('placeQuery', ''); this.active = -1; }
                            }
                        }"
                    >
                        <label for="place-input" class="ink-rule block font-serif text-xl font-bold">
                            Ort <span class="font-sans text-base font-normal text-muted">(optional)</span>
                        </label>
                        <p id="place-hint" class="text-muted">
                            Tippe den Anfang eines Ortsnamens, zum Beispiel „Gar“ für Gareth.
                        </p>
                        @error('placeQuery')
                            <p class="error-text" id="placeQuery-error"><span aria-hidden="true">⚠ </span>Fehler: {{ $message }}</p>
                        @enderror

                        <input
                            type="text"
                            id="place-input"
                            class="field-input"
                            role="combobox"
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                            aria-autocomplete="list"
                            aria-expanded="{{ $hasSuggestions ? 'true' : 'false' }}"
                            aria-controls="place-listbox"
                            aria-describedby="place-hint place-status{{ $errors->has('placeQuery') ? ' placeQuery-error' : '' }}"
                            @error('placeQuery') aria-invalid="true" @enderror
                            wire:model.live.debounce.250ms="placeQuery"
                            x-on:input="active = -1"
                            x-on:keydown.arrow-down.prevent="move(1)"
                            x-on:keydown.arrow-up.prevent="move(-1)"
                            x-on:keydown.enter="choose($event)"
                            x-on:keydown.escape="close($event)"
                            :aria-activedescendant="active >= 0 && options()[active] ? options()[active].id : null"
                        >

                        <p id="place-status" role="status" class="sr-only">
                            @if ($hasSuggestions)
                                {{ count($suggestions) }} {{ count($suggestions) === 1 ? 'Vorschlag' : 'Vorschläge' }} verfügbar.
                            @elseif ($placeSlug !== '')
                                Ort {{ $placeQuery }} ausgewählt.
                            @elseif (trim($placeQuery) !== '')
                                Keine passenden Orte gefunden.
                            @endif
                        </p>

                        <ul id="place-listbox" role="listbox" aria-label="Ortsvorschläge" x-ref="listbox" class="listbox" @unless ($hasSuggestions) hidden @endunless>
                            @foreach ($suggestions as $suggestion)
                                <li
                                    id="place-option-{{ $suggestion['slug'] }}"
                                    role="option"
                                    aria-selected="false"
                                    wire:key="place-option-{{ $suggestion['slug'] }}"
                                    wire:click="selectPlace('{{ $suggestion['slug'] }}')"
                                >
                                    <span class="font-semibold">{{ $suggestion['name'] }}</span>
                                    @if ($suggestion['region_name'])
                                        <span class="text-sm text-muted">{{ $suggestion['region_name'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        @if ($placeSlug !== '')
                            <p class="flex flex-wrap items-center gap-3">
                                <span>Ausgewählter Ort: <strong>{{ $placeQuery }}</strong></span>
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="clearPlace">Auswahl entfernen</button>
                            </p>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-3 pt-2">
                        @if ($placeSlug !== '' || trim($placeQuery) !== '')
                            <button type="button" class="btn btn-primary" wire:click="confirmRegion">Weiter</button>
                        @endif
                        <button type="button" class="btn {{ $placeSlug === '' && trim($placeQuery) === '' ? 'btn-primary' : 'btn-secondary' }}" wire:click="skipPlaceAndConfirmRegion">
                            Ohne Ort weiter
                        </button>
                    </div>
                @endif
            @elseif ($focusField === 'genres')
                <fieldset class="min-w-0 space-y-4" aria-describedby="genres-hint">
                    <legend id="genres-legend" tabindex="-1" class="ink-rule mb-1 font-serif text-xl font-bold">
                        Welche Genres sprechen euch an?
                    </legend>
                    <p id="genres-hint" class="text-muted">Mehrfachauswahl möglich. Ohne Auswahl berücksichtigen wir alle Genres.</p>
                    @error('genres.*')
                        <p class="error-text" id="genres-error"><span aria-hidden="true">⚠ </span>Fehler: {{ $message }}</p>
                    @enderror

                    @if (count($genreOptions) === 0)
                        <p class="text-muted">Derzeit sind keine Genres hinterlegt.</p>
                    @else
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($genreOptions as $index => $genre)
                                <label class="choice" wire:key="genre-{{ $genre['slug'] }}">
                                    <input
                                        type="checkbox"
                                        id="genres-{{ $index }}"
                                        name="genres[]"
                                        value="{{ $genre['slug'] }}"
                                        wire:model.live="genres"
                                    >
                                    <span><span class="choice-label">{{ $genre['name'] }}</span></span>
                                </label>
                            @endforeach
                        </div>
                    @endif

                    <div class="pt-2">
                        <button type="button" class="btn btn-primary" wire:click="continueGenres">Weiter</button>
                    </div>
                </fieldset>
            @elseif ($current)
                <fieldset class="min-w-0 space-y-4" @if ($current['hint']) aria-describedby="{{ $focusField }}-hint" @endif>
                    <legend id="focus-legend" tabindex="-1" class="ink-rule mb-1 font-serif text-xl font-bold">{{ $current['question'] }}</legend>
                    @if ($current['hint'])
                        <p id="{{ $focusField }}-hint" class="text-muted">{{ $current['hint'] }}</p>
                    @endif
                    @error($focusField)
                        <p class="error-text" id="{{ $focusField }}-error"><span aria-hidden="true">⚠ </span>Fehler: {{ $message }}</p>
                    @enderror

                    <div class="grid gap-3">
                        @foreach ($current['options'] as $index => $option)
                            <label class="choice" wire:key="{{ $focusField }}-{{ $option['value'] }}">
                                <input
                                    type="radio"
                                    id="{{ $focusField }}-{{ $index }}"
                                    name="{{ $focusField }}"
                                    value="{{ $option['value'] }}"
                                    wire:model.live="{{ $focusField }}"
                                    @error($focusField) aria-invalid="true" aria-describedby="{{ $focusField }}-error" @enderror
                                >
                                <span>
                                    <span class="choice-label">
                                        @if ($focusField === 'ruleset')
                                            @include('partials.edition-eye', ['edition' => match ($option['value']) {
                                                'dsa4' => 4,
                                                'dsa5' => 5,
                                                default => 0,
                                            }])
                                        @endif
                                        {{ $option['label'] }}
                                    </span>
                                    @if ($option['description'])
                                        <span class="choice-description">{{ $option['description'] }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif
        </div>
    @endif
</div>

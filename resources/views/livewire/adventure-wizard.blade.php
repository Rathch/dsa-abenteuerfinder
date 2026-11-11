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
                            <article class="card recommendation-card" aria-labelledby="adventure-{{ $card['id'] }}-title" data-testid="recommendation">
                                <header class="recommendation-heading space-y-1">
                                    <h3 id="adventure-{{ $card['id'] }}-title" class="recommendation-title">{{ $card['title'] }}</h3>
                                    <p class="text-muted">Reihe: {{ $card['series'] }}</p>
                                </header>

                                @if ($card['coverUrl'])
                                    <button
                                        type="button"
                                        class="cover-preview"
                                        data-cover-preview
                                        aria-expanded="false"
                                        aria-label="Umschlag von {{ $card['title'] }}"
                                    >
                                        <img
                                            src="{{ $card['coverUrl'] }}"
                                            alt=""
                                            width="96"
                                            height="128"
                                            class="cover-thumb"
                                            loading="lazy"
                                        >
                                    </button>
                                @else
                                    <img
                                        src="{{ asset('images/cover-fallback.svg') }}"
                                        alt=""
                                        width="96"
                                        height="128"
                                        class="cover-thumb"
                                        loading="lazy"
                                    >
                                @endif

                                <div class="recommendation-body space-y-5">
                                <section class="steckbrief" aria-label="Steckbrief">
                                    <dl class="steckbrief-grid">
                                        <div>
                                            <dt class="meta-label">
                                                @include('partials.meta-icon', ['icon' => 'map'])
                                                <span>Schauplatz</span>
                                            </dt>
                                            <dd>{{ $card['location'] }}</dd>
                                        </div>
                                        <div>
                                            <dt class="meta-label">
                                                @include('partials.meta-icon', ['icon' => 'book'])
                                                <span>Regeln</span>
                                            </dt>
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
                                        <div>
                                            <dt class="meta-label">
                                                @include('partials.meta-icon', ['icon' => 'hourglass'])
                                                <span>Dauer</span>
                                            </dt>
                                            <dd>{{ $card['duration'] }}</dd>
                                        </div>
                                        @if ($card['group'] !== null)
                                            <div>
                                                <dt>Gruppengröße</dt>
                                                <dd>{{ $card['group'] }}</dd>
                                            </div>
                                        @endif
                                        @if (count($card['genres']) > 0)
                                            <div class="steckbrief-genres">
                                                <dt class="meta-label">
                                                    @include('partials.meta-icon', ['icon' => 'tag'])
                                                    <span>Genre</span>
                                                </dt>
                                                <dd>
                                                    <ul class="flex flex-wrap gap-1.5" aria-label="Genres">
                                                        @foreach ($card['genres'] as $genre)
                                                            <li class="genre-tag">{{ $genre }}</li>
                                                        @endforeach
                                                    </ul>
                                                </dd>
                                            </div>
                                        @endif
                                    </dl>
                                </section>

                                @if ($card['descriptionKind'] === 'klappentext' || $card['descriptionKind'] === 'kurzbeschreibung')
                                    <div>
                                        <h4 class="meta-heading">{{ $card['descriptionKind'] === 'klappentext' ? 'Klappentext' : 'Kurzbeschreibung' }}</h4>
                                        <p class="mt-2">{{ $card['description'] }}</p>
                                        @if ($card['descriptionKind'] === 'klappentext')
                                            <p class="mt-2 text-sm text-muted">
                                                Quelle:
                                                <a href="{{ $card['blurbUrl'] }}" class="text-link" target="_blank" rel="noopener noreferrer">Wiki Aventurica<span class="sr-only"> (öffnet in neuem Tab)</span></a>;
                                                zur Weiterverwendung siehe
                                                <a href="https://de.wiki-aventurica.de/wiki/Wiki_Aventurica:Ulisses-Disclaimer" class="text-link" target="_blank" rel="noopener noreferrer">Ulisses-Disclaimer<span class="sr-only"> (öffnet in neuem Tab)</span></a>.
                                            </p>
                                        @else
                                            <p class="mt-2 text-sm text-muted">
                                                Quelle:
                                                <a href="{{ $card['wikiUrl'] }}" class="text-link" target="_blank" rel="noopener noreferrer">Wiki Aventurica<span class="sr-only"> (öffnet in neuem Tab)</span></a>,
                                                Artikel „{{ $card['title'] }}“,
                                                Lizenz
                                                <a href="https://creativecommons.org/licenses/by-sa/3.0/deed.de" class="text-link" target="_blank" rel="noopener noreferrer">CC BY-SA 3.0<span class="sr-only"> (öffnet in neuem Tab)</span></a>.
                                            </p>
                                        @endif
                                    </div>
                                @endif

                                <div class="recommendation-reasons">
                                    <h4 class="meta-heading">
                                        @include('partials.meta-icon', ['icon' => 'bulb'])
                                        <span>Warum passt es?</span>
                                    </h4>
                                    <ul class="mt-2 list-disc space-y-1 pl-6">
                                        @foreach ($card['reasons'] as $reason)
                                            <li>{{ $reason }}</li>
                                        @endforeach
                                    </ul>
                                </div>

                                <ul class="flex flex-wrap gap-3" aria-label="Weiterführende Links zu {{ $card['title'] }}">
                                    <li>
                                        <a href="{{ $card['wikiUrl'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">
                                            <span>Im Wiki Aventurica lesen<span class="sr-only"> (öffnet in neuem Tab)</span></span>
                                            @include('partials.external-icon')
                                        </a>
                                    </li>
                                </ul>
                                </div>
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
                'placeQuery' => 'place-list',
                'regionSlugs' => 'regionSlug-0',
                'regionConfirmed' => 'region-legend',
                'rulesDone' => 'ruleset-0',
                'exoticDone' => 'exotic-0',
                'timeframeDone' => 'timeframe-0',
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
                @elseif ($focusField === 'region')
                    In welcher Region soll das Abenteuer spielen?
                @elseif ($focusField === 'genres')
                    Welche Genres sprechen euch an?
                @elseif ($current)
                    {{ $current['question'] }}
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
                @php
                    $regionDescribedBy = ['regionSlug-hint'];
                    if ($errors->has('regionSlugs')) {
                        $regionDescribedBy[] = 'regionSlugs-error';
                    }
                    if ($errors->has('regionConfirmed')) {
                        $regionDescribedBy[] = 'regionConfirmed-error';
                    }
                    $regionInvalid = $errors->has('regionSlugs') || $errors->has('regionConfirmed');
                    $regionDescribedByAttr = implode(' ', $regionDescribedBy);
                @endphp
                <fieldset class="min-w-0 space-y-4" aria-describedby="{{ $regionDescribedByAttr }}" @if ($regionInvalid) aria-invalid="true" @endif>
                    <legend id="region-legend" tabindex="-1" class="ink-rule mb-1 font-serif text-xl font-bold">
                        In welcher Region soll das Abenteuer spielen?
                    </legend>
                    <p id="regionSlug-hint" class="text-muted">Mehrfachauswahl möglich. Mit „Egal“ entfällt der Ort. Bei genau einer Region kannst du zusätzlich einen Ort wählen.</p>
                    @error('regionSlugs')
                        <p class="error-text" id="regionSlugs-error"><span aria-hidden="true">⚠ </span>Fehler: {{ $message }}</p>
                    @enderror
                    @error('regionConfirmed')
                        <p class="error-text" id="regionConfirmed-error"><span aria-hidden="true">⚠ </span>Fehler: {{ $message }}</p>
                    @enderror

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($regionOptions as $index => $option)
                            @if ($option['value'] === 'egal')
                                <label class="choice" wire:key="region-egal">
                                    <input
                                        type="checkbox"
                                        id="regionSlug-{{ $index }}"
                                        name="regionsAny"
                                        value="1"
                                        wire:model.live="regionsAny"
                                        aria-describedby="{{ $regionDescribedByAttr }}"
                                        @if ($regionInvalid) aria-invalid="true" @endif
                                    >
                                    <span><span class="choice-label">{{ $option['label'] }}</span></span>
                                </label>
                            @else
                                <label class="choice" wire:key="region-{{ $option['value'] }}">
                                    <input
                                        type="checkbox"
                                        id="regionSlug-{{ $index }}"
                                        name="regionSlugs[]"
                                        value="{{ $option['value'] }}"
                                        wire:model.live="regionSlugs"
                                        aria-describedby="{{ $regionDescribedByAttr }}"
                                        @if ($regionInvalid) aria-invalid="true" @endif
                                    >
                                    <span><span class="choice-label">{{ $option['label'] }}</span></span>
                                </label>
                            @endif
                        @endforeach
                    </div>

                    @if (count($regionSlugs) === 1 && ! $regionsAny)
                        @php
                            $placeDescribedBy = 'place-hint'.($errors->has('placeQuery') ? ' placeQuery-error' : '');
                        @endphp
                        <div id="place-list" tabindex="-1" class="space-y-3" role="radiogroup" aria-labelledby="place-legend" aria-describedby="{{ $placeDescribedBy }}" @if ($errors->has('placeQuery')) aria-invalid="true" @endif>
                            <p id="place-legend" class="ink-rule font-serif text-xl font-bold">
                                Ort <span class="font-sans text-base font-normal text-muted">(optional)</span>
                            </p>
                            <p id="place-hint" class="text-muted">Diese Orte liegen in der gewählten Region. Ohne Auswahl bleibt der Ort offen.</p>
                            @error('placeQuery')
                                <p class="error-text" id="placeQuery-error"><span aria-hidden="true">⚠ </span>Fehler: {{ $message }}</p>
                            @enderror

                            @if (count($placeOptions) === 0)
                                <p class="text-muted">In dieser Region sind keine Orte hinterlegt.</p>
                            @else
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="choice" wire:key="place-none">
                                        <input
                                            type="radio"
                                            id="place-none"
                                            name="placeSlug"
                                            value=""
                                            wire:model.live="placeSlug"
                                            aria-describedby="{{ $placeDescribedBy }}"
                                            @if ($errors->has('placeQuery')) aria-invalid="true" @endif
                                        >
                                        <span><span class="choice-label">Kein bestimmter Ort</span></span>
                                    </label>
                                    @foreach ($placeOptions as $place)
                                        <label class="choice" wire:key="place-{{ $place['slug'] }}">
                                            <input
                                                type="radio"
                                                id="place-{{ $place['slug'] }}"
                                                name="placeSlug"
                                                value="{{ $place['slug'] }}"
                                                wire:model.live="placeSlug"
                                                aria-describedby="{{ $placeDescribedBy }}"
                                                @if ($errors->has('placeQuery')) aria-invalid="true" @endif
                                            >
                                            <span><span class="choice-label">{{ $place['name'] }}</span></span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="pt-2">
                        <button type="button" class="btn btn-primary" wire:click="confirmRegion">Weiter</button>
                    </div>
                </fieldset>
            @elseif (in_array($focusField, ['ruleset', 'exotic', 'timeframe'], true) && $current)
                @php
                    $multiModel = match ($focusField) {
                        'ruleset' => 'rules',
                        'exotic' => 'exotics',
                        default => 'timeframes',
                    };
                    $multiContinue = match ($focusField) {
                        'ruleset' => 'continueRules',
                        'exotic' => 'continueExotic',
                        default => 'continueTimeframe',
                    };
                    $multiAnyModel = match ($focusField) {
                        'ruleset' => 'rulesAny',
                        'exotic' => 'exoticAny',
                        default => null,
                    };
                    $multiDescribedBy = [];
                    if ($current['hint']) {
                        $multiDescribedBy[] = $focusField.'-hint';
                    }
                    $multiDescribedBy[] = $focusField.'-multi';
                    if ($errors->has($focusField.'Done')) {
                        $multiDescribedBy[] = $focusField.'-error';
                    }
                    $multiDescribedByAttr = implode(' ', $multiDescribedBy);
                @endphp
                <fieldset class="min-w-0 space-y-4" aria-describedby="{{ $multiDescribedByAttr }}" @if ($errors->has($focusField.'Done')) aria-invalid="true" @endif>
                    <legend id="focus-legend" tabindex="-1" class="ink-rule mb-1 font-serif text-xl font-bold">{{ $current['question'] }}</legend>
                    @if ($current['hint'])
                        <p id="{{ $focusField }}-hint" class="text-muted">{{ $current['hint'] }}</p>
                    @endif
                    <p id="{{ $focusField }}-multi" class="text-muted">Mehrfachauswahl möglich. Ohne Auswahl berücksichtigen wir alle Möglichkeiten.</p>
                    @error($focusField.'Done')
                        <p class="error-text" id="{{ $focusField }}-error"><span aria-hidden="true">⚠ </span>Fehler: {{ $message }}</p>
                    @enderror

                    <div class="grid gap-3">
                        @foreach ($current['options'] as $index => $option)
                            @if ($option['value'] === 'any')
                                <label class="choice" wire:key="{{ $focusField }}-any">
                                    <input
                                        type="checkbox"
                                        id="{{ $focusField }}-{{ $index }}"
                                        name="{{ $multiAnyModel }}"
                                        value="1"
                                        wire:model.live="{{ $multiAnyModel }}"
                                        aria-describedby="{{ $multiDescribedByAttr }}"
                                        @if ($errors->has($focusField.'Done')) aria-invalid="true" @endif
                                    >
                                    <span>
                                        <span class="choice-label">{{ $option['label'] }}</span>
                                        @if ($option['description'])
                                            <span class="choice-hint">{{ $option['description'] }}</span>
                                        @endif
                                    </span>
                                </label>
                            @else
                                <label class="choice" wire:key="{{ $focusField }}-{{ $option['value'] }}">
                                    <input
                                        type="checkbox"
                                        id="{{ $focusField }}-{{ $index }}"
                                        name="{{ $multiModel }}[]"
                                        value="{{ $option['value'] }}"
                                        wire:model.live="{{ $multiModel }}"
                                        aria-describedby="{{ $multiDescribedByAttr }}"
                                        @if ($errors->has($focusField.'Done')) aria-invalid="true" @endif
                                    >
                                    <span>
                                        <span class="choice-label">{{ $option['label'] }}</span>
                                        @if ($option['description'])
                                            <span class="choice-hint">{{ $option['description'] }}</span>
                                        @endif
                                    </span>
                                </label>
                            @endif
                        @endforeach
                    </div>

                    <div class="pt-2">
                        <button type="button" class="btn btn-primary" wire:click="{{ $multiContinue }}">Weiter</button>
                    </div>
                </fieldset>
            @elseif ($focusField === 'genres')
                @php
                    $genresInvalid = collect($errors->keys())->contains(fn (string $key): bool => $key === 'genres' || str_starts_with($key, 'genres.'));
                    $genresDescribedBy = 'genres-hint'.($genresInvalid ? ' genres-error' : '');
                @endphp
                <fieldset class="min-w-0 space-y-4" aria-describedby="{{ $genresDescribedBy }}" @if ($genresInvalid) aria-invalid="true" @endif>
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
                                        aria-describedby="{{ $genresDescribedBy }}"
                                        @if ($genresInvalid) aria-invalid="true" @endif
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
                @php
                    $radioContinue = match ($focusField) {
                        'gmExperience' => 'continueGmExperience',
                        'groupSize' => 'continueGroupSize',
                        default => 'continueExperience',
                    };
                    $choiceDescribedBy = array_values(array_filter([
                        $current['hint'] ? $focusField.'-hint' : null,
                        $errors->has($focusField) ? $focusField.'-error' : null,
                    ]));
                    $choiceDescribedByAttr = implode(' ', $choiceDescribedBy);
                @endphp
                <fieldset class="min-w-0 space-y-4" @if ($choiceDescribedByAttr !== '') aria-describedby="{{ $choiceDescribedByAttr }}" @endif @if ($errors->has($focusField)) aria-invalid="true" @endif>
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
                                    @if ($choiceDescribedByAttr !== '') aria-describedby="{{ $choiceDescribedByAttr }}" @endif
                                    @if ($errors->has($focusField)) aria-invalid="true" @endif
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

                    <div class="pt-2">
                        <button type="button" class="btn btn-primary" wire:click="{{ $radioContinue }}">Weiter</button>
                    </div>
                </fieldset>
            @endif

            @if ($focusField !== 'ruleset' && $focusField !== 'review')
                <div>
                    <button type="button" class="btn btn-secondary" wire:click="restart">Neu starten</button>
                </div>
            @endif
        </div>
    @endif
</div>

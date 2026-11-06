<?php

namespace App\Livewire;

use App\Domain\Matching\AdventureCandidate;
use App\Domain\Matching\DurationWish;
use App\Domain\Matching\ExoticWish;
use App\Domain\Matching\ExperienceLevel;
use App\Domain\Matching\GroupSizeWish;
use App\Domain\Matching\LocationType;
use App\Domain\Matching\MatchResult;
use App\Domain\Matching\RegionKey;
use App\Domain\Matching\RulesWish;
use App\Domain\Matching\WizardAnswers;
use App\Services\AdventureMatchService;
use App\Services\LocationAutocompleteService;
use App\Services\WizardOptionsService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Progressive Reveal: eine Frage im Fokus, gegenseitige Facetten, Skip/Auto bei leeren
 * oder eineindeutigen Optionen. Submit erst im Review.
 *
 * @property-read list<array{slug: string, name: string, region_slug: ?string, region_name: ?string}> $suggestions
 * @property-read MatchResult|null $matchResult
 */
class AdventureWizard extends Component
{
    private const SESSION_KEY = 'adventure_wizard';

    private const EGAL = 'egal';

    public const FOCUS_REVIEW = 'review';

    public bool $showResults = false;

    /** Aktives Panel: Feldname, region, genres oder review. */
    public string $focusField = 'ruleset';

    public bool $regionConfirmed = false;

    public bool $genresDone = false;

    public string $experienceLevel = '';

    public string $groupSize = '';

    public string $timeframe = '';

    public string $regionSlug = '';

    public string $placeQuery = '';

    public string $placeSlug = '';

    public string $exotic = '';

    /** @var list<string> */
    public array $genres = [];

    public string $ruleset = '';

    public string $gmExperience = '';

    public function mount(): void
    {
        /** @var mixed $stored */
        $stored = session()->get(self::SESSION_KEY);
        if (! is_array($stored)) {
            $this->advanceToNextOpenField(announce: false);

            return;
        }

        $this->experienceLevel = $this->storedString($stored, 'experienceLevel');
        $this->groupSize = $this->storedString($stored, 'groupSize');
        $this->timeframe = $this->storedString($stored, 'timeframe');
        $this->regionSlug = $this->storedString($stored, 'regionSlug');
        $this->placeQuery = $this->storedString($stored, 'placeQuery');
        $this->placeSlug = $this->storedString($stored, 'placeSlug');
        $this->exotic = $this->storedString($stored, 'exotic');
        $this->ruleset = $this->storedString($stored, 'rules');
        $this->gmExperience = $this->storedString($stored, 'gmExperience');
        $this->regionConfirmed = ($stored['regionConfirmed'] ?? false) === true;
        $this->genresDone = ($stored['genresDone'] ?? false) === true;

        $genres = $stored['genres'] ?? [];
        $this->genres = is_array($genres)
            ? array_values(array_filter($genres, is_string(...)))
            : [];

        $this->showResults = ($stored['showResults'] ?? false) === true && $this->answersComplete();
        $this->scrubInvalidAnswers();

        if ($this->showResults) {
            return;
        }

        $storedFocus = $this->storedString($stored, 'focusField');
        if ($storedFocus !== '' && ($storedFocus === self::FOCUS_REVIEW || in_array($storedFocus, $this->fieldOrder(), true))) {
            $this->focusField = $storedFocus;
        }

        $this->advanceToNextOpenField(announce: false);
    }

    public function submit(): void
    {
        $this->scrubInvalidAnswers();
        $this->advanceToNextOpenField(announce: false);

        if (! $this->validateForm()) {
            if (! $this->answersComplete()) {
                $this->advanceToNextOpenField();
            }

            return;
        }

        $this->showResults = true;
        $this->persist();
        $this->dispatch('wizard-focus', target: 'results-heading');
    }

    public function editAnswers(): void
    {
        $this->resetErrorBag();
        $this->showResults = false;
        $this->focusField = self::FOCUS_REVIEW;
        $this->persist();
        $this->dispatch('wizard-focus', target: 'review-heading');
    }

    public function editField(string $field): void
    {
        if (! in_array($field, $this->fieldOrder(), true) && $field !== self::FOCUS_REVIEW) {
            return;
        }

        $this->resetErrorBag();
        $this->showResults = false;
        $this->focusField = $field;

        if ($field === 'region') {
            $this->regionConfirmed = false;
        }

        if ($field === 'genres') {
            $this->genresDone = false;
        }

        $this->persist();
        $this->dispatch('wizard-focus', target: $this->focusTargetId($field));
    }

    public function restart(): void
    {
        $this->resetErrorBag();
        $this->reset([
            'showResults', 'experienceLevel', 'groupSize', 'timeframe', 'regionSlug',
            'placeQuery', 'placeSlug', 'exotic', 'genres', 'ruleset', 'gmExperience',
            'regionConfirmed', 'genresDone',
        ]);
        $this->focusField = 'ruleset';
        session()->forget(self::SESSION_KEY);
        $this->advanceToNextOpenField(announce: false);
        $this->dispatch('wizard-focus', target: 'form-heading');
    }

    public function confirmRegion(): void
    {
        if ($this->regionSlug === '') {
            return;
        }

        if (! $this->resolveTypedPlace()) {
            $this->addError('placeQuery', 'Bitte wähle einen Ort aus der Vorschlagsliste oder leere das Feld.');
            $this->dispatch('wizard-focus', target: 'error-summary');

            return;
        }

        $this->resetErrorBag('placeQuery');
        $this->regionConfirmed = true;
        $this->scrubInvalidAnswers();
        $this->persist();
        $this->advanceToNextOpenField();
    }

    public function skipPlaceAndConfirmRegion(): void
    {
        $this->placeQuery = '';
        $this->placeSlug = '';
        $this->resetErrorBag('placeQuery');
        $this->regionConfirmed = true;
        $this->scrubInvalidAnswers();
        $this->persist();
        $this->advanceToNextOpenField();
    }

    public function continueGenres(): void
    {
        $this->genresDone = true;
        $this->scrubInvalidAnswers();
        $this->persist();
        $this->advanceToNextOpenField();
    }

    public function selectPlace(string $slug): void
    {
        foreach ($this->placeSearch($this->placeQuery) as $place) {
            if ($place['slug'] === $slug) {
                $this->applyPlace($place);
                $this->resetErrorBag('placeQuery');
                $this->scrubInvalidAnswers();
                $this->persist();
                $this->dispatch('wizard-focus', target: 'place-input');

                return;
            }
        }
    }

    /**
     * @param  array{slug: string, name: string, region_slug: ?string, region_name: ?string}  $place
     */
    private function applyPlace(array $place): void
    {
        $this->placeSlug = $place['slug'];
        $this->placeQuery = $place['name'];

        if (($this->regionSlug === '' || $this->regionSlug === self::EGAL)
            && $place['region_slug'] !== null
            && RegionKey::tryFrom($place['region_slug']) !== null) {
            $this->regionSlug = $place['region_slug'];
        }
    }

    public function clearPlace(): void
    {
        $this->placeQuery = '';
        $this->placeSlug = '';
        $this->scrubInvalidAnswers();
        $this->persist();
        $this->dispatch('wizard-focus', target: 'place-input');
    }

    public function updatedPlaceQuery(): void
    {
        $this->placeSlug = '';
    }

    public function updatedRegionSlug(): void
    {
        if ($this->placeSlug !== '' && $this->regionSlug !== '' && $this->regionSlug !== self::EGAL) {
            $placeRegion = app(LocationAutocompleteService::class)->regionSlugFor($this->placeSlug);
            if ($placeRegion !== $this->regionSlug) {
                $this->placeQuery = '';
                $this->placeSlug = '';
            }
        }

        $this->regionConfirmed = false;
        $this->scrubInvalidAnswers();
        $this->persist();
        // Region-Panel bleibt offen für optionalen Ort.
    }

    public function updatedRuleset(): void
    {
        $this->afterRadioChange();
    }

    public function updatedExperienceLevel(): void
    {
        $this->afterRadioChange();
    }

    public function updatedExotic(): void
    {
        $this->afterRadioChange();
    }

    public function updatedTimeframe(): void
    {
        $this->afterRadioChange();
    }

    public function updatedGenres(): void
    {
        $this->genresDone = false;
        $this->scrubInvalidAnswers();
        $this->persist();
    }

    public function updatedGmExperience(): void
    {
        $this->afterRadioChange();
    }

    public function updatedGroupSize(): void
    {
        $this->afterRadioChange();
    }

    private function afterRadioChange(): void
    {
        $this->scrubInvalidAnswers();
        $this->persist();
        $this->advanceToNextOpenField();
    }

    /**
     * @return list<string>
     */
    public function fieldOrder(): array
    {
        return [
            'ruleset',
            'experienceLevel',
            'region',
            'exotic',
            'timeframe',
            'genres',
            'gmExperience',
            'groupSize',
        ];
    }

    /**
     * Setzt Fokus auf die nächste Frage, die Nutzeraktion braucht; autofill/skip dazwischen.
     */
    public function advanceToNextOpenField(bool $announce = true): void
    {
        $options = app(WizardOptionsService::class);

        foreach ($this->fieldOrder() as $field) {
            if ($this->fieldIsSatisfied($field, $options)) {
                continue;
            }

            if ($this->tryAutoComplete($field, $options)) {
                continue;
            }

            $this->focusField = $field;
            $this->persist();

            if ($announce) {
                $this->dispatch('wizard-focus', target: $this->focusTargetId($field));
            }

            return;
        }

        $this->focusField = self::FOCUS_REVIEW;
        $this->persist();

        if ($announce) {
            $this->dispatch('wizard-focus', target: 'review-heading');
        }
    }

    private function fieldIsSatisfied(string $field, WizardOptionsService $options): bool
    {
        return match ($field) {
            'ruleset' => $this->ruleset !== '',
            'experienceLevel' => $this->experienceLevel !== '',
            'region' => $this->regionSlug !== '' && $this->regionConfirmed,
            'exotic' => $this->exotic !== '',
            'timeframe' => $this->timeframe !== '',
            'genres' => $this->genresDone,
            'gmExperience' => $this->gmExperience !== '',
            'groupSize' => $this->groupSize !== '',
            default => true,
        };
    }

    /**
     * Skip/Auto: true = Feld erledigt ohne Nutzeraktion.
     */
    private function tryAutoComplete(string $field, WizardOptionsService $options): bool
    {
        $facets = $this->toFacetAnswers();

        return match ($field) {
            'ruleset' => $this->autoFillProperty('ruleset', $options->availableRulesWishes($facets)),
            'experienceLevel' => $this->autoFillProperty('experienceLevel', $options->availableExperienceLevels($facets)),
            'region' => $this->autoFillRegion($options, $facets),
            'exotic' => $this->autoFillProperty('exotic', $options->availableExoticWishes($facets)),
            'timeframe' => $this->autoFillProperty('timeframe', $options->availableTimeframes($facets)),
            'genres' => $this->autoSkipEmptyGenres($options, $facets),
            'gmExperience' => $this->autoFillProperty('gmExperience', $options->availableGmExperienceLevels($facets)),
            'groupSize' => $this->autoFillProperty('groupSize', $options->availableGroupSizes($facets)),
            default => false,
        };
    }

    /**
     * @param  'ruleset'|'experienceLevel'|'exotic'|'timeframe'|'gmExperience'|'groupSize'  $property
     * @param  list<string>  $available
     */
    private function autoFillProperty(string $property, array $available): bool
    {
        if ($this->{$property} !== '') {
            return true;
        }

        if ($available === []) {
            // Keine Optionen: überspringen geht bei Pflichtfeldern nicht sinnvoll – Nutzer sieht leeres Review/Validierung.
            return false;
        }

        if (count($available) === 1) {
            $this->{$property} = $available[0];
            $this->scrubInvalidAnswers();

            return $this->{$property} !== '';
        }

        return false;
    }

    private function autoFillRegion(WizardOptionsService $options, WizardAnswers $facets): bool
    {
        if ($this->regionSlug !== '' && $this->regionConfirmed) {
            return true;
        }

        $slugs = $options->availableRegionSlugs($facets);
        if ($slugs === [] && $this->regionSlug === '') {
            $this->regionSlug = self::EGAL;
            $this->regionConfirmed = true;
            $this->scrubInvalidAnswers();

            return true;
        }

        return false;
    }

    private function autoSkipEmptyGenres(WizardOptionsService $options, WizardAnswers $facets): bool
    {
        if ($this->genresDone) {
            return true;
        }

        if ($options->availableGenres($facets) === []) {
            $this->genresDone = true;

            return true;
        }

        return false;
    }

    private function focusTargetId(string $field): string
    {
        return match ($field) {
            self::FOCUS_REVIEW => 'review-heading',
            'region' => 'region-legend',
            'genres' => 'genres-legend',
            default => 'focus-legend',
        };
    }

    /**
     * @return list<array{slug: string, name: string, region_slug: ?string, region_name: ?string}>
     */
    #[Computed]
    public function suggestions(): array
    {
        if ($this->placeSlug !== '' || trim($this->placeQuery) === '') {
            return [];
        }

        return $this->placeSearch($this->placeQuery);
    }

    #[Computed]
    public function matchResult(): ?MatchResult
    {
        if (! $this->showResults || ! $this->answersComplete()) {
            return null;
        }

        return app(AdventureMatchService::class)->recommend($this->toAnswers());
    }

    public function toAnswers(): WizardAnswers
    {
        return new WizardAnswers(
            experienceLevel: $this->experienceLevel !== '' ? $this->experienceLevel : 'einsteiger',
            groupSize: $this->groupSize === '' || $this->groupSize === self::EGAL ? null : $this->groupSize,
            timeframe: $this->timeframe !== '' ? $this->timeframe : 'evening_long',
            regionSlug: $this->regionSlug === '' || $this->regionSlug === self::EGAL ? null : $this->regionSlug,
            placeSlug: $this->placeSlug === '' ? null : $this->placeSlug,
            exotic: $this->exotic !== '' ? $this->exotic : 'any',
            genres: $this->genres,
            rules: $this->ruleset !== '' ? $this->ruleset : 'any',
            gmExperience: $this->gmExperience !== '' ? $this->gmExperience : 'einsteiger',
        );
    }

    public function toFacetAnswers(): WizardAnswers
    {
        return new WizardAnswers(
            experienceLevel: $this->experienceLevel,
            groupSize: $this->groupSize === '' || $this->groupSize === self::EGAL ? null : $this->groupSize,
            timeframe: $this->timeframe,
            regionSlug: $this->regionSlug === '' || $this->regionSlug === self::EGAL ? null : $this->regionSlug,
            placeSlug: $this->placeSlug === '' ? null : $this->placeSlug,
            exotic: $this->exotic,
            genres: $this->genres,
            rules: $this->ruleset,
            gmExperience: $this->gmExperience,
        );
    }

    public function render(): View
    {
        $options = app(WizardOptionsService::class);
        $facets = $this->toFacetAnswers();
        $result = $this->matchResult;
        $fields = $this->fieldConfig($options, $facets);

        return view('livewire.adventure-wizard', [
            'fields' => $fields,
            'fieldsByKey' => collect($fields)->keyBy('field'),
            'genreOptions' => $options->availableGenres($facets),
            'regionOptions' => $options->regionOptions($options->availableRegionSlugs($facets)),
            'summaryItems' => $this->summaryItems($options, $facets),
            'progress' => $this->progressRatio(),
            'cards' => $result === null ? [] : $this->cards($result, $options->genreNames()),
            'fallbackNotes' => $result->fallbackNotes ?? [],
        ]);
    }

    /**
     * @return list<array{field: string, question: string, hint: ?string, options: list<array{value: string, label: string, description: ?string}>}>
     */
    private function fieldConfig(WizardOptionsService $options, WizardAnswers $facets): array
    {
        return [
            [
                'field' => 'ruleset',
                'question' => 'Nach welchem Regelwerk wollt ihr spielen?',
                'hint' => 'Im Katalog: DSA 5, DSA 4.1 und editionsunabhängige Abenteuer. „Egal“ schließt editionsunabhängige mit ein.',
                'options' => $options->rulesOptions($options->availableRulesWishes($facets)),
            ],
            [
                'field' => 'experienceLevel',
                'question' => 'Wie erfahren sind die Heldinnen und Helden eurer Gruppe?',
                'hint' => 'Gemeint ist die Spielerfahrung der Figuren, nicht die der Spielleitung.',
                'options' => $options->experienceOptions($options->availableExperienceLevels($facets)),
            ],
            [
                'field' => 'exotic',
                'question' => 'Wie exotisch darf das Abenteuer sein?',
                'hint' => null,
                'options' => $options->exoticOptions($options->availableExoticWishes($facets)),
            ],
            [
                'field' => 'timeframe',
                'question' => 'Wie viel Zeit wollt ihr investieren?',
                'hint' => null,
                'options' => $options->timeframeOptions($options->availableTimeframes($facets)),
            ],
            [
                'field' => 'gmExperience',
                'question' => 'Wie erfahren ist die Spielleitung?',
                'hint' => 'Davon hängt ab, wie viel Vorbereitung und Improvisation das Abenteuer verlangen darf.',
                'options' => $options->gmExperienceOptions($options->availableGmExperienceLevels($facets)),
            ],
            [
                'field' => 'groupSize',
                'question' => 'Wie viele Spielende seid ihr (ohne Spielleitung)?',
                'hint' => null,
                'options' => $options->groupSizeOptions($options->availableGroupSizes($facets)),
            ],
        ];
    }

    /**
     * @return list<array{field: string, label: string, value: string}>
     */
    private function summaryItems(WizardOptionsService $options, WizardAnswers $facets): array
    {
        $items = [];
        $byField = collect($this->fieldConfig($options, $facets))->keyBy('field');

        foreach ($this->fieldOrder() as $field) {
            $label = match ($field) {
                'ruleset' => 'Regeln',
                'experienceLevel' => 'Helden',
                'region' => 'Region',
                'exotic' => 'Exotik',
                'timeframe' => 'Zeit',
                'genres' => 'Genres',
                'gmExperience' => 'Spielleitung',
                'groupSize' => 'Gruppe',
                default => $field,
            };

            $value = match ($field) {
                'ruleset' => $this->optionLabel($byField->get('ruleset')['options'] ?? [], $this->ruleset),
                'experienceLevel' => $this->optionLabel($byField->get('experienceLevel')['options'] ?? [], $this->experienceLevel),
                'region' => $this->regionSummaryLabel($options, $facets),
                'exotic' => $this->optionLabel($byField->get('exotic')['options'] ?? [], $this->exotic),
                'timeframe' => $this->optionLabel($byField->get('timeframe')['options'] ?? [], $this->timeframe),
                'genres' => $this->genresSummaryLabel($options, $facets),
                'gmExperience' => $this->optionLabel($byField->get('gmExperience')['options'] ?? [], $this->gmExperience),
                'groupSize' => $this->optionLabel($byField->get('groupSize')['options'] ?? [], $this->groupSize),
                default => null,
            };

            if ($value === null) {
                continue;
            }

            $items[] = ['field' => $field, 'label' => $label, 'value' => $value];
        }

        return $items;
    }

    /**
     * @param  list<array{value: string, label: string, description: ?string}>  $options
     */
    private function optionLabel(array $options, string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        foreach ($options as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return $value;
    }

    private function regionSummaryLabel(WizardOptionsService $options, WizardAnswers $facets): ?string
    {
        if ($this->regionSlug === '' || ! $this->regionConfirmed) {
            return null;
        }

        foreach ($options->regionOptions($options->availableRegionSlugs($facets)) as $option) {
            if ($option['value'] === $this->regionSlug) {
                $label = $option['label'];
                if ($this->placeSlug !== '' && $this->placeQuery !== '') {
                    return $label.' · '.$this->placeQuery;
                }

                return $label;
            }
        }

        return $this->regionSlug;
    }

    private function genresSummaryLabel(WizardOptionsService $options, WizardAnswers $facets): ?string
    {
        if (! $this->genresDone) {
            return null;
        }

        if ($this->genres === []) {
            return 'Egal';
        }

        $names = [];
        foreach ($options->availableGenres($facets) as $genre) {
            if (in_array($genre['slug'], $this->genres, true)) {
                $names[] = $genre['name'];
            }
        }

        foreach ($options->genres() as $genre) {
            if (in_array($genre['slug'], $this->genres, true) && ! in_array($genre['name'], $names, true)) {
                $names[] = $genre['name'];
            }
        }

        return $names === [] ? 'Egal' : implode(', ', $names);
    }

    /**
     * @return array{current: int, total: int, ratio: float}
     */
    private function progressRatio(): array
    {
        $checks = [
            $this->ruleset !== '',
            $this->experienceLevel !== '',
            $this->regionSlug !== '' && $this->regionConfirmed,
            $this->exotic !== '',
            $this->timeframe !== '',
            $this->gmExperience !== '',
            $this->groupSize !== '',
        ];
        $done = count(array_filter($checks));
        $total = count($checks);

        return [
            'current' => $done,
            'total' => $total,
            'ratio' => $done / $total,
        ];
    }

    /**
     * @param  array<string, string>  $genreNames
     * @return list<array{id: int, title: string, series: string, location: string, duration: string, rules: string, edition: ?int, group: ?string, genres: list<string>, description: string, reasons: list<string>, wikiUrl: string}>
     */
    private function cards(MatchResult $result, array $genreNames): array
    {
        $cards = [];

        foreach ($result->recommendations as $recommendation) {
            $adventure = $recommendation->adventure;

            $cards[] = [
                'id' => $adventure->id,
                'title' => $adventure->title,
                'series' => $adventure->seriesName,
                'location' => $this->locationLabel($adventure),
                'duration' => $adventure->durationCategory->label(),
                'rules' => $adventure->ruleVersion->label(),
                'edition' => match ($adventure->ruleVersion->value) {
                    'dsa4' => 4,
                    'dsa5' => 5,
                    default => null,
                },
                'group' => $this->groupLabel($adventure->groupSizeMin, $adventure->groupSizeMax),
                'genres' => array_map(
                    static fn (string $slug): string => $genreNames[$slug] ?? ucfirst($slug),
                    $adventure->genres,
                ),
                'description' => $adventure->shortDescription,
                'reasons' => $recommendation->reasons,
                'wikiUrl' => $adventure->wikiUrl,
            ];
        }

        return $cards;
    }

    private function locationLabel(AdventureCandidate $adventure): string
    {
        $region = $adventure->primaryRegionSlug !== null ? RegionKey::labelFor($adventure->primaryRegionSlug) : null;

        $label = match (true) {
            $adventure->primaryPlaceName !== null && $region !== null => $adventure->primaryPlaceName.' ('.$region.')',
            $adventure->primaryPlaceName !== null => $adventure->primaryPlaceName,
            $region !== null => $region,
            default => 'Schauplatz offen',
        };

        if ($adventure->locationType === LocationType::Traveling) {
            return $label.', unterwegs';
        }

        $others = array_values(array_filter(
            $adventure->regionSlugs,
            static fn (string $slug): bool => $slug !== $adventure->primaryRegionSlug,
        ));

        return $others === []
            ? $label
            : $label.', auch '.implode(', ', array_map(RegionKey::labelFor(...), $others));
    }

    private function groupLabel(?int $min, ?int $max): ?string
    {
        return match (true) {
            $min !== null && $max !== null && $min === $max => $min.' Spielende',
            $min !== null && $max !== null => $min.' bis '.$max.' Spielende',
            $min !== null => 'ab '.$min.' Spielenden',
            $max !== null => 'bis '.$max.' Spielende',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function formRules(): array
    {
        $options = app(WizardOptionsService::class);

        return [
            'ruleset' => ['required', Rule::in(array_column(RulesWish::cases(), 'value'))],
            'experienceLevel' => ['required', Rule::in(array_column(ExperienceLevel::cases(), 'value'))],
            'regionSlug' => ['required', Rule::in([self::EGAL, ...array_column(RegionKey::cases(), 'value')])],
            'placeQuery' => ['nullable', 'string', 'max:80'],
            'exotic' => ['required', Rule::in(array_column(ExoticWish::cases(), 'value'))],
            'timeframe' => ['required', Rule::in(array_column(DurationWish::cases(), 'value'))],
            'genres' => ['array'],
            'genres.*' => ['string', Rule::in(array_column($options->genres(), 'slug'))],
            'gmExperience' => ['required', Rule::in(array_column(ExperienceLevel::cases(), 'value'))],
            'groupSize' => ['required', Rule::in(['egal', ...array_column(GroupSizeWish::cases(), 'value')])],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationMessages(): array
    {
        return [
            'experienceLevel.required' => 'Bitte wähle aus, wie erfahren die Helden sind.',
            'experienceLevel.in' => 'Bitte wähle eine der angebotenen Erfahrungsstufen.',
            'groupSize.required' => 'Bitte wähle eine Gruppengröße (oder „Egal“).',
            'groupSize.in' => 'Bitte wähle eine der angebotenen Gruppengrößen.',
            'timeframe.required' => 'Bitte wähle einen Zeitrahmen.',
            'timeframe.in' => 'Bitte wähle einen der angebotenen Zeitrahmen.',
            'regionSlug.required' => 'Bitte wähle eine Region (oder „Egal“).',
            'regionSlug.in' => 'Bitte wähle eine der angebotenen Regionen.',
            'placeQuery.max' => 'Der Ortsname ist zu lang.',
            'exotic.required' => 'Bitte wähle, wie exotisch das Abenteuer sein darf.',
            'exotic.in' => 'Bitte wähle eine der angebotenen Optionen.',
            'genres.*.in' => 'Bitte wähle nur Genres aus der Liste.',
            'ruleset.required' => 'Bitte wähle ein Regelwerk (oder „Egal“).',
            'ruleset.in' => 'Bitte wähle eines der angebotenen Regelwerke.',
            'gmExperience.required' => 'Bitte wähle die Erfahrung der Spielleitung.',
            'gmExperience.in' => 'Bitte wähle eine der angebotenen Erfahrungsstufen.',
        ];
    }

    private function validateForm(): bool
    {
        try {
            if (! $this->resolveTypedPlace()) {
                $this->focusField = 'region';
                $this->regionConfirmed = false;
                $this->addError('placeQuery', 'Bitte wähle einen Ort aus der Vorschlagsliste oder leere das Feld.');
                $this->dispatch('wizard-focus', target: 'error-summary');

                return false;
            }

            $this->validate($this->formRules(), $this->validationMessages());
        } catch (ValidationException $exception) {
            $this->advanceToNextOpenField(announce: false);
            $this->dispatch('wizard-focus', target: 'error-summary');

            throw $exception;
        }

        $this->resetErrorBag();

        return true;
    }

    private function resolveTypedPlace(): bool
    {
        $query = trim($this->placeQuery);
        if ($query === '') {
            $this->placeSlug = '';

            return true;
        }

        if ($this->placeSlug !== '') {
            return true;
        }

        foreach ($this->placeSearch($query) as $place) {
            if (mb_strtolower($place['name']) === mb_strtolower($query)) {
                $this->applyPlace($place);

                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{slug: string, name: string, region_slug: ?string, region_name: ?string}>
     */
    private function placeSearch(string $query): array
    {
        $region = $this->regionSlug !== '' && $this->regionSlug !== self::EGAL
            ? $this->regionSlug
            : null;

        return app(LocationAutocompleteService::class)->search($query, regionSlug: $region);
    }

    private function scrubInvalidAnswers(): void
    {
        $options = app(WizardOptionsService::class);
        $answers = $this->toFacetAnswers();

        if ($this->ruleset !== '' && ! in_array($this->ruleset, $options->availableRulesWishes($answers), true)) {
            $this->ruleset = '';
        }

        $answers = $this->toFacetAnswers();
        if ($this->experienceLevel !== '' && ! in_array($this->experienceLevel, $options->availableExperienceLevels($answers), true)) {
            $this->experienceLevel = '';
        }

        $answers = $this->toFacetAnswers();
        $regions = [self::EGAL, ...$options->availableRegionSlugs($answers)];
        if ($this->regionSlug !== '' && ! in_array($this->regionSlug, $regions, true)) {
            $this->regionSlug = '';
            $this->placeQuery = '';
            $this->placeSlug = '';
            $this->regionConfirmed = false;
        }

        $answers = $this->toFacetAnswers();
        if ($this->exotic !== '' && ! in_array($this->exotic, $options->availableExoticWishes($answers), true)) {
            $this->exotic = '';
        }

        $answers = $this->toFacetAnswers();
        if ($this->timeframe !== '' && ! in_array($this->timeframe, $options->availableTimeframes($answers), true)) {
            $this->timeframe = '';
        }

        $answers = $this->toFacetAnswers();
        $genreSlugs = array_column($options->availableGenres($answers), 'slug');
        $this->genres = array_values(array_filter(
            $this->genres,
            static fn (string $slug): bool => in_array($slug, $genreSlugs, true),
        ));
        if ($genreSlugs === []) {
            $this->genresDone = true;
        }

        $answers = $this->toFacetAnswers();
        if ($this->gmExperience !== '' && ! in_array($this->gmExperience, $options->availableGmExperienceLevels($answers), true)) {
            $this->gmExperience = '';
        }

        $answers = $this->toFacetAnswers();
        if ($this->groupSize !== '' && ! in_array($this->groupSize, $options->availableGroupSizes($answers), true)) {
            $this->groupSize = '';
        }
    }

    private function answersComplete(): bool
    {
        return $this->experienceLevel !== ''
            && $this->groupSize !== ''
            && $this->timeframe !== ''
            && $this->regionSlug !== ''
            && $this->regionConfirmed
            && $this->exotic !== ''
            && $this->ruleset !== ''
            && $this->gmExperience !== '';
    }

    private function persist(): void
    {
        session()->put(self::SESSION_KEY, [
            'showResults' => $this->showResults,
            'focusField' => $this->focusField,
            'regionConfirmed' => $this->regionConfirmed,
            'genresDone' => $this->genresDone,
            'experienceLevel' => $this->experienceLevel,
            'groupSize' => $this->groupSize,
            'timeframe' => $this->timeframe,
            'regionSlug' => $this->regionSlug,
            'placeQuery' => $this->placeQuery,
            'placeSlug' => $this->placeSlug,
            'exotic' => $this->exotic,
            'genres' => $this->genres,
            'rules' => $this->ruleset,
            'gmExperience' => $this->gmExperience,
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $stored
     */
    private function storedString(array $stored, string $key): string
    {
        $value = $stored[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}

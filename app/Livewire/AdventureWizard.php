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
use App\Domain\Matching\RuleVersion;
use App\Domain\Matching\WizardAnswers;
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

    private ?WizardOptionsService $wizardOptions = null;

    public bool $showResults = false;

    /** Aktives Panel: Feldname, region, genres oder review. */
    public string $focusField = 'ruleset';

    public bool $regionConfirmed = false;

    public bool $genresDone = false;

    public bool $rulesDone = false;

    public bool $timeframeDone = false;

    public bool $exoticDone = false;

    public string $experienceLevel = '';

    public string $groupSize = '';

    /** @var list<string> */
    public array $timeframes = [];

    /** @var list<string> */
    public array $regionSlugs = [];

    public bool $regionsAny = false;

    public string $placeQuery = '';

    public string $placeSlug = '';

    /** @var list<string> */
    public array $exotics = [];

    public bool $exoticAny = false;

    /** @var list<string> */
    public array $genres = [];

    /** @var list<string> */
    public array $rules = [];

    public bool $rulesAny = false;

    public string $gmExperience = '';

    public function mount(): void
    {
        /** @var mixed $stored */
        $stored = session()->get(self::SESSION_KEY);
        if (! is_array($stored)) {
            $this->advanceToNextOpenField(announce: false);

            return;
        }

        $restored = WizardAnswers::fromSession($stored);
        $this->experienceLevel = $restored->experienceLevel;
        $this->groupSize = $this->storedString($stored, 'groupSize');
        $this->gmExperience = $restored->gmExperience;
        $this->genres = $restored->genres;
        $this->rules = $restored->rules;
        $this->rulesAny = $restored->rulesAny;
        $this->regionSlugs = $restored->regionSlugs;
        $this->regionsAny = $restored->regionsAny;
        $this->timeframes = $restored->timeframes;
        $this->exotics = $restored->exotics;
        $this->exoticAny = $restored->exoticAny;
        $this->placeSlug = $restored->placeSlug ?? '';
        $this->placeQuery = $this->placeSlug === '' ? '' : $this->storedString($stored, 'placeQuery');
        $this->rulesDone = is_array($stored['rules'] ?? null) && ($stored['rulesDone'] ?? false) === true;
        $this->timeframeDone = is_array($stored['timeframes'] ?? null) && ($stored['timeframeDone'] ?? false) === true;
        $this->exoticDone = is_array($stored['exotics'] ?? null) && ($stored['exoticDone'] ?? false) === true;
        $this->regionConfirmed = is_array($stored['regionSlugs'] ?? null) && ($stored['regionConfirmed'] ?? false) === true;
        $this->genresDone = ($stored['genresDone'] ?? false) === true;

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

        if ($field === 'ruleset') {
            $this->rulesDone = false;
        }

        if ($field === 'exotic') {
            $this->exoticDone = false;
        }

        if ($field === 'timeframe') {
            $this->timeframeDone = false;
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
            'showResults', 'experienceLevel', 'groupSize', 'timeframes', 'regionSlugs',
            'regionsAny', 'placeQuery', 'placeSlug', 'exotics', 'exoticAny', 'genres',
            'rules', 'rulesAny', 'gmExperience', 'regionConfirmed', 'genresDone',
            'rulesDone', 'timeframeDone', 'exoticDone',
        ]);
        $this->focusField = 'ruleset';
        session()->forget(self::SESSION_KEY);
        $this->advanceToNextOpenField(announce: false);
        $this->dispatch('wizard-focus', target: 'form-heading');
    }

    public function continueRules(): void
    {
        $this->finishMultiStep('rulesDone');
    }

    public function continueTimeframe(): void
    {
        $this->finishMultiStep('timeframeDone');
    }

    public function continueExotic(): void
    {
        $this->finishMultiStep('exoticDone');
    }

    private function finishMultiStep(string $flag): void
    {
        $this->{$flag} = true;
        $this->resetErrorBag();
        $this->scrubInvalidAnswers();
        $this->persist();
        $this->advanceToNextOpenField();
    }

    public function confirmRegion(): void
    {
        if ($this->regionSlugs === [] && ! $this->regionsAny) {
            $this->addError('regionConfirmed', 'Bitte wähle eine Region oder „Egal“.');
            $this->dispatch('wizard-focus', target: 'error-summary');

            return;
        }

        if (! $this->resolveTypedPlace()) {
            $this->addError('placeQuery', 'Bitte wähle einen Ort aus der Vorschlagsliste oder leere das Feld.');
            $this->dispatch('wizard-focus', target: 'error-summary');

            return;
        }

        $this->resetErrorBag();
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
        $this->resetErrorBag();
        $this->scrubInvalidAnswers();
        $this->persist();
        $this->advanceToNextOpenField();
    }

    public function togglePlace(string $slug): void
    {
        if (count($this->regionSlugs) !== 1 || $this->regionsAny) {
            return;
        }

        if ($this->placeSlug === $slug) {
            $this->placeSlug = '';
            $this->placeQuery = '';
        } else {
            $place = null;
            foreach ($this->placesForSelectedRegion() as $candidate) {
                if ($candidate['slug'] === $slug) {
                    $place = $candidate;
                    break;
                }
            }

            if ($place === null) {
                return;
            }

            $this->placeSlug = $place['slug'];
            $this->placeQuery = $place['name'];
        }

        $this->resetErrorBag('placeQuery');
        $this->scrubInvalidAnswers();
        $this->persist();
    }

    public function updatedPlaceSlug(): void
    {
        if (count($this->regionSlugs) !== 1 || $this->regionsAny) {
            $this->placeSlug = '';
            $this->placeQuery = '';

            return;
        }

        if ($this->placeSlug === '') {
            $this->placeQuery = '';
        } else {
            $match = null;
            foreach ($this->placesForSelectedRegion() as $candidate) {
                if ($candidate['slug'] === $this->placeSlug) {
                    $match = $candidate;
                    break;
                }
            }

            if ($match === null) {
                $this->placeSlug = '';
                $this->placeQuery = '';

                return;
            }

            $this->placeQuery = $match['name'];
        }

        $this->resetErrorBag('placeQuery');
        $this->scrubInvalidAnswers();
        $this->persist();
    }

    public function selectPlace(string $slug): void
    {
        foreach ($this->placeSearch($this->placeQuery) as $place) {
            if ($place['slug'] === $slug) {
                $this->applyPlace($place);
                $this->resetErrorBag('placeQuery');
                $this->scrubInvalidAnswers();
                $this->persist();
                $this->dispatch('wizard-focus', target: 'place-list');

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

        if (count($this->regionSlugs) === 1 && $place['region_slug'] !== null && $place['region_slug'] !== $this->regionSlugs[0]) {
            $this->placeSlug = '';
            $this->placeQuery = '';
        }
    }

    public function clearPlace(): void
    {
        $this->placeQuery = '';
        $this->placeSlug = '';
        $this->scrubInvalidAnswers();
        $this->persist();
        $this->dispatch('wizard-focus', target: 'place-list');
    }

    public function updatedPlaceQuery(): void
    {
        $this->placeSlug = '';
    }

    public function updatedRegionsAny(): void
    {
        if ($this->regionsAny) {
            $this->regionSlugs = [];
            $this->placeQuery = '';
            $this->placeSlug = '';
        }

        $this->regionConfirmed = false;
        $this->scrubInvalidAnswers();
        $this->persist();
    }

    public function updatedRegionSlugs(): void
    {
        if ($this->regionSlugs !== []) {
            $this->regionsAny = false;
        }

        if (count($this->regionSlugs) !== 1) {
            $this->placeQuery = '';
            $this->placeSlug = '';
        } elseif ($this->placeSlug !== '') {
            $placeRegion = app(LocationAutocompleteService::class)->regionSlugFor($this->placeSlug);
            if ($placeRegion !== $this->regionSlugs[0]) {
                $this->placeQuery = '';
                $this->placeSlug = '';
            }
        }

        $this->regionConfirmed = false;
        $this->scrubInvalidAnswers();
        $this->persist();
    }

    public function updatedRulesAny(): void
    {
        if ($this->rulesAny) {
            $this->rules = [];
        }

        $this->rulesDone = false;
        $this->scrubInvalidAnswers();
        $this->persist();
    }

    public function updatedRules(): void
    {
        if ($this->rules !== []) {
            $this->rulesAny = false;
        }

        $this->rulesDone = false;
        $this->scrubInvalidAnswers();
        $this->persist();
    }

    public function continueExperience(): void
    {
        $this->finishChoice('experienceLevel');
    }

    public function updatedExperienceLevel(): void
    {
        $this->rememberChoice();
    }

    public function updatedExoticAny(): void
    {
        if ($this->exoticAny) {
            $this->exotics = [];
        }

        $this->exoticDone = false;
        $this->scrubInvalidAnswers();
        $this->persist();
    }

    public function updatedExotics(): void
    {
        if ($this->exotics !== []) {
            $this->exoticAny = false;
        }

        $this->exoticDone = false;
        $this->scrubInvalidAnswers();
        $this->persist();
    }

    public function updatedTimeframes(): void
    {
        $this->timeframeDone = false;
        $this->scrubInvalidAnswers();
        $this->persist();
    }

    public function updatedGenres(): void
    {
        $this->genresDone = false;
        $this->scrubInvalidAnswers();
        $this->persist();
    }

    public function continueGmExperience(): void
    {
        $this->finishChoice('gmExperience');
    }

    public function updatedGmExperience(): void
    {
        $this->rememberChoice();
    }

    public function continueGroupSize(): void
    {
        $this->finishChoice('groupSize');
    }

    public function updatedGroupSize(): void
    {
        $this->rememberChoice();
    }

    /**
     * @param  'experienceLevel'|'gmExperience'|'groupSize'  $property
     */
    private function finishChoice(string $property): void
    {
        if ($this->{$property} === '') {
            $this->addError($property, $this->validationMessages()[$property.'.required']);
            $this->dispatch('wizard-focus', target: 'error-summary');

            return;
        }

        $this->resetErrorBag();
        $this->scrubInvalidAnswers();
        $this->persist();
        $this->advanceToNextOpenField();
    }

    private function rememberChoice(): void
    {
        $this->resetErrorBag();
        $this->scrubInvalidAnswers();
        $this->persist();
    }

    /**
     * @return list<string>
     */
    public function fieldOrder(): array
    {
        return [
            'ruleset',
            'experienceLevel',
            'exotic',
            'region',
            'timeframe',
            'genres',
            'gmExperience',
            'groupSize',
        ];
    }

    private function wizardOptions(): WizardOptionsService
    {
        return $this->wizardOptions ??= app(WizardOptionsService::class);
    }

    /**
     * Setzt Fokus auf die nächste Frage, die Nutzeraktion braucht; autofill/skip dazwischen.
     */
    public function advanceToNextOpenField(bool $announce = true): void
    {
        $options = $this->wizardOptions();

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
            'ruleset' => $this->rulesDone,
            'experienceLevel' => $this->experienceLevel !== '',
            'region' => $this->regionConfirmed && ($this->regionsAny || $this->regionSlugs !== []),
            'exotic' => $this->exoticDone,
            'timeframe' => $this->timeframeDone,
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
            'ruleset' => false,
            'experienceLevel' => $this->autoFillProperty('experienceLevel', $options->availableExperienceLevels($facets)),
            'region' => $this->autoFillRegion($options, $facets),
            'exotic' => false,
            'timeframe' => false,
            'genres' => $this->autoSkipEmptyGenres($options, $facets),
            'gmExperience' => $this->autoFillProperty('gmExperience', $options->availableGmExperienceLevels($facets)),
            'groupSize' => $this->autoFillProperty('groupSize', $options->availableGroupSizes($facets)),
            default => false,
        };
    }

    /**
     * @param  'experienceLevel'|'gmExperience'|'groupSize'  $property
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
        if ($this->regionConfirmed && ($this->regionsAny || $this->regionSlugs !== [])) {
            return true;
        }

        $slugs = $options->availableRegionSlugs($facets);
        if ($slugs === [] && $this->regionSlugs === [] && ! $this->regionsAny) {
            $this->regionsAny = true;
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

        return $this->wizardOptions()->recommend($this->toAnswers());
    }

    public function toAnswers(): WizardAnswers
    {
        return $this->answersFor(
            experienceLevel: $this->experienceLevel !== '' ? $this->experienceLevel : 'einsteiger',
            gmExperience: $this->gmExperience !== '' ? $this->gmExperience : 'einsteiger',
        );
    }

    public function toFacetAnswers(): WizardAnswers
    {
        return $this->answersFor(
            experienceLevel: $this->experienceLevel,
            gmExperience: $this->gmExperience,
        );
    }

    private function answersFor(string $experienceLevel, string $gmExperience): WizardAnswers
    {
        return new WizardAnswers(
            experienceLevel: $experienceLevel,
            groupSize: $this->groupSize === '' || $this->groupSize === self::EGAL ? null : $this->groupSize,
            timeframes: $this->timeframes,
            regionSlugs: $this->regionSlugs,
            regionsAny: $this->regionsAny,
            placeSlug: $this->placeSlug === '' ? null : $this->placeSlug,
            exotics: $this->exotics,
            exoticAny: $this->exoticAny,
            genres: $this->genres,
            rules: $this->rules,
            rulesAny: $this->rulesAny,
            gmExperience: $gmExperience,
        );
    }

    public function render(): View
    {
        $options = $this->wizardOptions();
        $facets = $this->toFacetAnswers();
        $result = $this->matchResult;
        $fields = $this->fieldConfig($options, $facets);

        return view('livewire.adventure-wizard', [
            'fields' => $fields,
            'fieldsByKey' => collect($fields)->keyBy('field'),
            'genreOptions' => $options->availableGenres($facets),
            'regionOptions' => $options->regionOptions($options->availableRegionSlugs($facets)),
            'placeOptions' => $this->placesForSelectedRegion(),
            'summaryItems' => $this->summaryItems($options, $facets, $fields),
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
                'hint' => 'Im Katalog: DSA 1 bis DSA 5 und editionsunabhängige Abenteuer. „Egal“ schließt alle Editionen ein.',
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
                'hint' => '„Nur Aventurien“ blendet die anderen Kontinente und das Tiefenreich aus. „Egal“ lässt sie zu.',
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
     * @param  list<array{field: string, question: string, hint: ?string, options: list<array{value: string, label: string, description: ?string}>}>  $fields
     * @return list<array{field: string, label: string, value: string}>
     */
    private function summaryItems(WizardOptionsService $options, WizardAnswers $facets, array $fields): array
    {
        $items = [];
        $byField = collect($fields)->keyBy('field');

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
                'ruleset' => $this->multiSummaryLabel($byField->get('ruleset')['options'] ?? [], $this->rules, $this->rulesAny, $this->rulesDone),
                'experienceLevel' => $this->optionLabel($byField->get('experienceLevel')['options'] ?? [], $this->experienceLevel),
                'region' => $this->regionSummaryLabel($options, $facets),
                'exotic' => $this->multiSummaryLabel($byField->get('exotic')['options'] ?? [], $this->exotics, $this->exoticAny, $this->exoticDone),
                'timeframe' => $this->multiSummaryLabel($byField->get('timeframe')['options'] ?? [], $this->timeframes, false, $this->timeframeDone),
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

    /**
     * @param  list<array{value: string, label: string, description: ?string}>  $options
     * @param  list<string>  $selected
     */
    private function multiSummaryLabel(array $options, array $selected, bool $any, bool $done): ?string
    {
        if (! $done && ! $any) {
            return null;
        }

        if ($any) {
            return 'Egal';
        }

        if ($selected === []) {
            return 'Keine Einschränkung';
        }

        $labels = [];
        foreach ($selected as $value) {
            $labels[] = $this->optionLabel($options, $value) ?? $value;
        }

        return implode(', ', $labels);
    }

    private function regionSummaryLabel(WizardOptionsService $options, WizardAnswers $facets): ?string
    {
        if (! $this->regionConfirmed || (! $this->regionsAny && $this->regionSlugs === [])) {
            return null;
        }

        if ($this->regionsAny) {
            return 'Egal';
        }

        $labels = [];
        foreach ($options->regionOptions($options->availableRegionSlugs($facets)) as $option) {
            if (in_array($option['value'], $this->regionSlugs, true)) {
                $labels[] = $option['label'];
            }
        }

        if ($labels === []) {
            $labels = array_map(RegionKey::labelFor(...), $this->regionSlugs);
        }

        $label = implode(', ', $labels);
        if (count($this->regionSlugs) === 1 && $this->placeSlug !== '' && $this->placeQuery !== '') {
            return $label.' · '.$this->placeQuery;
        }

        return $label;
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
            $this->rulesDone,
            $this->experienceLevel !== '',
            $this->regionConfirmed && ($this->regionsAny || $this->regionSlugs !== []),
            $this->exoticDone,
            $this->timeframeDone,
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
     * @return list<array{id: int, title: string, series: string, location: string, duration: string, rules: string, edition: ?int, group: ?string, genres: list<string>, description: string, reasons: list<string>, wikiUrl: string, blurbUrl: string, coverUrl: ?string}>
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
                'edition' => $adventure->ruleVersion->editionNumber(),
                'group' => $this->groupLabel($adventure->groupSizeMin, $adventure->groupSizeMax),
                'genres' => array_map(
                    static fn (string $slug): string => $genreNames[$slug] ?? ucfirst($slug),
                    $adventure->genres,
                ),
                'description' => $adventure->shortDescription,
                'reasons' => $recommendation->reasons,
                'wikiUrl' => $adventure->wikiUrl,
                'blurbUrl' => $this->blurbUrl($adventure->wikiUrl),
                'coverUrl' => $adventure->coverUrl,
            ];
        }

        return $cards;
    }

    private function blurbUrl(string $wikiUrl): string
    {
        $base = rtrim($wikiUrl, '/');
        if (str_ends_with($base, '/Klappentext')) {
            return $base;
        }

        return $base.'/Klappentext';
    }

    private function locationLabel(AdventureCandidate $adventure): string
    {
        $region = $adventure->primaryRegionSlug !== null ? RegionKey::labelFor($adventure->primaryRegionSlug) : null;

        $place = $adventure->primaryPlaceName ?? $adventure->placeLabel;

        $label = match (true) {
            $place !== null && $region !== null => $place.' ('.$region.')',
            $place !== null => $place,
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
        $options = $this->wizardOptions();

        return [
            'rules' => ['array'],
            'rules.*' => ['string', Rule::in(array_map(
                static fn (RuleVersion $version): string => $version->value,
                array_filter(RuleVersion::cases(), static fn (RuleVersion $version): bool => $version !== RuleVersion::EditionUnabhaengig),
            ))],
            'rulesDone' => ['accepted'],
            'experienceLevel' => ['required', Rule::in(array_column(ExperienceLevel::cases(), 'value'))],
            'regionSlugs' => ['array'],
            'regionSlugs.*' => ['string', Rule::in(array_column(RegionKey::cases(), 'value'))],
            'regionConfirmed' => ['accepted'],
            'placeQuery' => ['nullable', 'string', 'max:80'],
            'exotics' => ['array'],
            'exotics.*' => ['string', Rule::in(array_filter(
                array_column(ExoticWish::cases(), 'value'),
                static fn (string $value): bool => $value !== ExoticWish::Any->value,
            ))],
            'exoticDone' => ['accepted'],
            'timeframes' => ['array'],
            'timeframes.*' => ['string', Rule::in(array_column(DurationWish::cases(), 'value'))],
            'timeframeDone' => ['accepted'],
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
            'timeframes.*.in' => 'Bitte wähle einen der angebotenen Zeitrahmen.',
            'timeframeDone.accepted' => 'Bitte bestätige den Zeitrahmen.',
            'regionSlugs.*.in' => 'Bitte wähle eine der angebotenen Regionen.',
            'regionConfirmed.accepted' => 'Bitte wähle eine Region oder „Egal“.',
            'placeQuery.max' => 'Der Ortsname ist zu lang.',
            'exotics.*.in' => 'Bitte wähle eine der angebotenen Optionen.',
            'exoticDone.accepted' => 'Bitte bestätige die Exotik.',
            'genres.*.in' => 'Bitte wähle nur Genres aus der Liste.',
            'rules.*.in' => 'Bitte wähle eines der angebotenen Regelwerke.',
            'rulesDone.accepted' => 'Bitte wähle ein Regelwerk oder „Egal“.',
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
        $region = count($this->regionSlugs) === 1 && ! $this->regionsAny
            ? $this->regionSlugs[0]
            : null;

        return app(LocationAutocompleteService::class)->search($query, regionSlug: $region);
    }

    /**
     * @return list<array{slug: string, name: string}>
     */
    private function placesForSelectedRegion(): array
    {
        if (count($this->regionSlugs) !== 1 || $this->regionsAny) {
            return [];
        }

        return app(LocationAutocompleteService::class)->placesInRegion($this->regionSlugs[0]);
    }

    private function scrubInvalidAnswers(): void
    {
        $options = $this->wizardOptions();
        $answers = $this->toFacetAnswers();

        $availableRules = $options->availableRulesWishes($answers);
        $keptRules = array_values(array_filter(
            $this->rules,
            static fn (string $value): bool => in_array($value, $availableRules, true),
        ));
        if ($keptRules !== $this->rules) {
            $this->rules = $keptRules;
        }
        if ($this->rulesAny && ! in_array('any', $availableRules, true)) {
            $this->rulesAny = false;
            $this->rulesDone = false;
        }

        $answers = $this->toFacetAnswers();
        if ($this->experienceLevel !== '' && ! in_array($this->experienceLevel, $options->availableExperienceLevels($answers), true)) {
            $this->experienceLevel = '';
        }

        $answers = $this->toFacetAnswers();
        $regions = $options->availableRegionSlugs($answers);
        $keptRegions = array_values(array_filter(
            $this->regionSlugs,
            static fn (string $slug): bool => in_array($slug, $regions, true),
        ));
        if ($keptRegions !== $this->regionSlugs) {
            $this->regionSlugs = $keptRegions;
            $this->placeQuery = '';
            $this->placeSlug = '';
            $this->regionConfirmed = false;
        }

        $answers = $this->toFacetAnswers();
        $availableExotic = $options->availableExoticWishes($answers);
        $keptExotics = array_values(array_filter(
            $this->exotics,
            static fn (string $value): bool => in_array($value, $availableExotic, true),
        ));
        if ($keptExotics !== $this->exotics) {
            $this->exotics = $keptExotics;
        }
        if ($this->exoticAny && ! in_array(ExoticWish::Any->value, $availableExotic, true)) {
            $this->exoticAny = false;
            $this->exoticDone = false;
        }

        $answers = $this->toFacetAnswers();
        $availableTimeframes = $options->availableTimeframes($answers);
        $keptTimeframes = array_values(array_filter(
            $this->timeframes,
            static fn (string $value): bool => in_array($value, $availableTimeframes, true),
        ));
        if ($keptTimeframes !== $this->timeframes) {
            $this->timeframes = $keptTimeframes;
        }

        $answers = $this->toFacetAnswers();
        $genreSlugs = array_column($options->availableGenres($answers), 'slug');
        $keptGenres = array_values(array_filter(
            $this->genres,
            static fn (string $slug): bool => in_array($slug, $genreSlugs, true),
        ));
        if ($keptGenres !== $this->genres) {
            $this->genres = $keptGenres;
        }
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
        return $this->rulesDone
            && $this->experienceLevel !== ''
            && $this->groupSize !== ''
            && $this->timeframeDone
            && $this->regionConfirmed
            && ($this->regionsAny || $this->regionSlugs !== [])
            && $this->exoticDone
            && $this->gmExperience !== '';
    }

    private function persist(): void
    {
        session()->put(self::SESSION_KEY, [
            'showResults' => $this->showResults,
            'focusField' => $this->focusField,
            'regionConfirmed' => $this->regionConfirmed,
            'genresDone' => $this->genresDone,
            'rulesDone' => $this->rulesDone,
            'timeframeDone' => $this->timeframeDone,
            'exoticDone' => $this->exoticDone,
            'experienceLevel' => $this->experienceLevel,
            'groupSize' => $this->groupSize,
            'timeframes' => $this->timeframes,
            'regionSlugs' => $this->regionSlugs,
            'regionsAny' => $this->regionsAny,
            'placeQuery' => $this->placeQuery,
            'placeSlug' => $this->placeSlug,
            'exotics' => $this->exotics,
            'exoticAny' => $this->exoticAny,
            'genres' => $this->genres,
            'rules' => $this->rules,
            'rulesAny' => $this->rulesAny,
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

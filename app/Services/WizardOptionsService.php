<?php

namespace App\Services;

use App\Domain\Matching\DurationWish;
use App\Domain\Matching\ExoticWish;
use App\Domain\Matching\ExperienceLevel;
use App\Domain\Matching\GroupSizeWish;
use App\Domain\Matching\MatchResult;
use App\Domain\Matching\RegionKey;
use App\Domain\Matching\RuleVersion;
use App\Domain\Matching\WizardAnswers;
use App\Domain\Matching\WizardFacetFilter;
use App\Models\GenreTag;

/**
 * Auswahlmöglichkeiten für den Wizard: Genres aus der DB und Facetten aus dem noch möglichen Abenteuer-Pool.
 */
class WizardOptionsService
{
    /** @var list<array{slug: string, name: string}>|null */
    private ?array $genreCatalog = null;

    public function __construct(
        private readonly AdventureMatchService $matches = new AdventureMatchService,
        private readonly WizardFacetFilter $facets = new WizardFacetFilter,
    ) {}

    public function recommend(WizardAnswers $answers): MatchResult
    {
        return $this->matches->recommend($answers);
    }

    /**
     * @return list<array{slug: string, name: string}>
     */
    public function genres(): array
    {
        if ($this->genreCatalog !== null) {
            return $this->genreCatalog;
        }

        $options = [];

        foreach (GenreTag::query()->orderBy('name')->get() as $tag) {
            $options[] = ['slug' => $tag->slug, 'name' => $tag->name];
        }

        return $this->genreCatalog = $options;
    }

    /**
     * @return array<string, string> Genre-Slug => Anzeigename
     */
    public function genreNames(): array
    {
        $names = [];

        foreach ($this->genres() as $genre) {
            $names[$genre['slug']] = $genre['name'];
        }

        return $names;
    }

    /**
     * Genres, die im aktuellen Pool noch vorkommen.
     *
     * @return list<array{slug: string, name: string}>
     */
    public function availableGenres(WizardAnswers $answers): array
    {
        $catalog = $this->genres();
        $allowed = array_flip($this->facets->genreSlugs(
            $answers,
            $this->matches->candidates(),
            array_column($catalog, 'slug'),
        ));

        return array_values(array_filter(
            $catalog,
            static fn (array $genre): bool => isset($allowed[$genre['slug']]),
        ));
    }

    /**
     * @return list<string>
     */
    public function availableExperienceLevels(WizardAnswers $answers): array
    {
        return $this->facets->experienceLevels($answers, $this->matches->candidates());
    }

    /**
     * @return list<string>
     */
    public function availableGroupSizes(WizardAnswers $answers): array
    {
        return $this->facets->groupSizes($answers, $this->matches->candidates());
    }

    /**
     * @return list<string>
     */
    public function availableTimeframes(WizardAnswers $answers): array
    {
        return $this->facets->timeframes($answers, $this->matches->candidates());
    }

    /**
     * @return list<string> Region-Slugs ohne „egal“
     */
    public function availableRegionSlugs(WizardAnswers $answers): array
    {
        return $this->facets->regionSlugs($answers, $this->matches->candidates());
    }

    /**
     * @return list<string>
     */
    public function availableExoticWishes(WizardAnswers $answers): array
    {
        return $this->facets->exoticWishes($answers, $this->matches->candidates());
    }

    /**
     * @return list<string>
     */
    public function availableRulesWishes(WizardAnswers $answers): array
    {
        return $this->facets->rulesWishes($answers, $this->matches->candidates());
    }

    /**
     * @return list<string>
     */
    public function availableGmExperienceLevels(WizardAnswers $answers): array
    {
        return $this->facets->gmExperienceLevels($answers, $this->matches->candidates());
    }

    /**
     * @param  list<string>  $values
     * @return list<array{value: string, label: string, description: ?string}>
     */
    public function experienceOptions(array $values): array
    {
        $meta = [
            'einsteiger' => ['Einsteiger', 'Frische Helden am Anfang ihrer Laufbahn.'],
            'fortgeschritten' => ['Fortgeschrittene', 'Helden mit einigen bestandenen Abenteuern.'],
            'erfahren' => ['Erfahrene', 'Bewährte Helden mit Namen und Ruf.'],
            'meister' => ['Meister', 'Legendäre Helden, die schon fast alles gesehen haben.'],
        ];

        return $this->mapOptions($values, $meta);
    }

    /**
     * @param  list<string>  $values
     * @return list<array{value: string, label: string, description: ?string}>
     */
    public function groupSizeOptions(array $values): array
    {
        $options = [];
        foreach ($values as $value) {
            if ($value === 'egal') {
                $options[] = ['value' => 'egal', 'label' => 'Egal', 'description' => 'Die Gruppengröße spielt keine Rolle.'];

                continue;
            }

            $wish = GroupSizeWish::tryFrom($value);
            if ($wish === null) {
                continue;
            }

            $options[] = [
                'value' => $wish->value,
                'label' => $wish->label(),
                'description' => $wish === GroupSizeWish::ThreeToFive ? 'Die klassische Heldengruppe.' : null,
            ];
        }

        return $options;
    }

    /**
     * @param  list<string>  $values
     * @return list<array{value: string, label: string, description: ?string}>
     */
    public function timeframeOptions(array $values): array
    {
        $meta = [
            DurationWish::EveningShort->value => ['Ein kurzer Abend', 'Etwa zwei bis drei Stunden.'],
            DurationWish::EveningLong->value => ['Ein langer Abend', 'Ein ganzer Spieleabend, gern mit Pausen.'],
            DurationWish::Multi->value => ['Mehrere Sitzungen', 'Ein Abenteuer, das sich über einige Abende erstreckt.'],
            DurationWish::Campaign->value => ['Eine Kampagne', 'Eine lange Geschichte für Monate.'],
        ];

        return $this->mapOptions($values, $meta);
    }

    /**
     * @param  list<string>  $values
     * @return list<array{value: string, label: string, description: ?string}>
     */
    public function exoticOptions(array $values): array
    {
        $meta = [
            ExoticWish::No->value => ['Nur Aventurien', 'Nur der Kontinent Aventurien.'],
            ExoticWish::ExtraAventurian->value => ['Andere Kontinente', 'Myranor, Uthuria, Tharun, Rakshazar oder das Tiefenreich.'],
            ExoticWish::UnusualCultures->value => ['Ungewöhnliche Kulturen', 'Fremde Bräuche und seltene Schauplätze.'],
            ExoticWish::Any->value => ['Egal', 'Aventurien und die anderen Kontinente.'],
        ];

        return $this->mapOptions($values, $meta);
    }

    /**
     * @param  list<string>  $values
     * @return list<array{value: string, label: string, description: ?string}>
     */
    public function rulesOptions(array $values): array
    {
        $meta = [];
        foreach (RuleVersion::cases() as $version) {
            if ($version === RuleVersion::EditionUnabhaengig) {
                continue;
            }
            $meta[$version->value] = [$version->label(), null];
        }
        $meta['any'] = ['Egal', 'Schließt alle Editionen und editionsunabhängige Abenteuer mit ein.'];

        return $this->mapOptions($values, $meta);
    }

    /**
     * @param  list<string>  $values
     * @return list<array{value: string, label: string, description: ?string}>
     */
    public function gmExperienceOptions(array $values): array
    {
        $meta = [
            ExperienceLevel::Einsteiger->value => ['Einsteiger', 'Ich leite zum ersten Mal oder erst selten.'],
            ExperienceLevel::Fortgeschritten->value => ['Fortgeschrittene', 'Ich habe schon einige Runden geleitet.'],
            ExperienceLevel::Erfahren->value => ['Erfahrene', 'Ich leite regelmäßig und improvisiere gern.'],
            ExperienceLevel::Meister->value => ['Meister', 'Ich leite seit vielen Jahren und fürchte keine Komplexität.'],
        ];

        return $this->mapOptions($values, $meta);
    }

    /**
     * @param  list<string>  $regionSlugs
     * @return list<array{value: string, label: string}>
     */
    public function regionOptions(array $regionSlugs): array
    {
        $options = [['value' => 'egal', 'label' => 'Egal']];

        foreach ($regionSlugs as $slug) {
            $key = RegionKey::tryFrom($slug);
            $options[] = [
                'value' => $slug,
                'label' => $key?->label() ?? RegionKey::labelFor($slug),
            ];
        }

        return $options;
    }

    /**
     * @param  list<string>  $values
     * @param  array<string, array{0: string, 1: ?string}>  $meta
     * @return list<array{value: string, label: string, description: ?string}>
     */
    private function mapOptions(array $values, array $meta): array
    {
        $options = [];

        foreach ($values as $value) {
            if (! isset($meta[$value])) {
                continue;
            }

            [$label, $description] = $meta[$value];
            $options[] = ['value' => $value, 'label' => $label, 'description' => $description];
        }

        return $options;
    }
}

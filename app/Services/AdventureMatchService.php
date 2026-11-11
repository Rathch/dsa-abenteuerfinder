<?php

namespace App\Services;

use App\Domain\Matching\AdventureCandidate;
use App\Domain\Matching\MatchFinder;
use App\Domain\Matching\MatchResult;
use App\Domain\Matching\WizardAnswers;
use App\Models\Adventure;
use App\Models\GenreTag;
use App\Models\Location;

/**
 * Lädt die im Wiki gelisteten Abenteuer und lässt den reinen {@see MatchFinder} bewerten.
 */
class AdventureMatchService
{
    /** @var list<AdventureCandidate>|null */
    private ?array $candidates = null;

    public function __construct(private readonly MatchFinder $finder = new MatchFinder) {}

    public function recommend(WizardAnswers $answers): MatchResult
    {
        return $this->finder->find($answers, $this->candidates());
    }

    /**
     * @return list<AdventureCandidate>
     */
    public function candidates(): array
    {
        if ($this->candidates !== null) {
            return $this->candidates;
        }

        $candidates = [];

        $adventures = Adventure::query()
            ->where('missing_from_wiki', false)
            ->with(['series', 'genreTags', 'regions', 'primaryLocation', 'primaryRegion'])
            ->orderBy('id')
            ->get();

        $regionByPlace = [];
        $places = Location::query()->where('type', 'place')->with('parent')->get();
        foreach ($places as $place) {
            $regionSlug = $place->parent?->slug;
            if (is_string($regionSlug) && $regionSlug !== '') {
                $regionByPlace[$place->slug] = $regionSlug;
            }
        }
        $regionsByKey = AdventureCandidate::regionIndex($regionByPlace);

        foreach ($adventures as $adventure) {
            $regionSlugs = array_values(array_unique(array_merge(
                array_map(static fn (Location $region): string => $region->slug, $adventure->regions->all()),
                AdventureCandidate::regionsForLabel($adventure->place_label, $regionsByKey),
            )));
            $candidates[] = new AdventureCandidate(
                id: $adventure->id,
                slug: $adventure->slug,
                title: $adventure->title,
                seriesSlug: $adventure->series->slug,
                seriesName: $adventure->series->name,
                experienceLevel: $adventure->experience_level,
                pageCount: $adventure->page_count,
                durationCategory: $adventure->duration_category,
                ruleVersion: $adventure->rule_version,
                exotic: $adventure->exotic,
                complexity: $adventure->complexity,
                groupSizeMin: $adventure->group_size_min,
                groupSizeMax: $adventure->group_size_max,
                locationType: $adventure->location_type,
                primaryPlaceSlug: $adventure->primaryLocation?->slug,
                primaryPlaceName: $adventure->primaryLocation?->name,
                primaryRegionSlug: $adventure->primaryRegion?->slug,
                regionSlugs: $regionSlugs,
                genres: array_values(array_map(static fn (GenreTag $tag): string => $tag->slug, $adventure->genreTags->all())),
                shortDescription: $adventure->short_description,
                wikiUrl: $adventure->wiki_url,
                incomplete: $adventure->incomplete,
                coverUrl: $adventure->cover_url,
                placeLabel: $adventure->place_label,
                missingFromWiki: $adventure->missing_from_wiki,
                klappentext: $adventure->klappentext,
            );
        }

        return $this->candidates = $candidates;
    }
}

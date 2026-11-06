<?php

namespace App\Services;

use App\Domain\Matching\LocationKind;
use App\Models\Location;

/**
 * Präfix-Suche nach Orten (für das Autocomplete im Wizard).
 * Der Vergleich ignoriert Groß-/Kleinschreibung, Umlaute und Apostrophe ("alan" findet "Al'Anfa").
 */
class LocationAutocompleteService
{
    /**
     * @return list<array{slug: string, name: string, region_slug: ?string, region_name: ?string}>
     */
    public function search(string $prefix, int $limit = 8, ?string $regionSlug = null): array
    {
        $needle = $this->fold($prefix);
        if ($needle === '' || $limit < 1) {
            return [];
        }

        $matches = [];
        $places = Location::query()
            ->where('type', LocationKind::Place->value)
            ->with('parent')
            ->orderBy('name')
            ->get();

        foreach ($places as $place) {
            if ($regionSlug !== null && $place->parent?->slug !== $regionSlug) {
                continue;
            }

            if (str_starts_with($this->fold($place->name), $needle)) {
                $matches[] = [
                    'slug' => $place->slug,
                    'name' => $place->name,
                    'region_slug' => $place->parent?->slug,
                    'region_name' => $place->parent?->name,
                ];
            }
        }

        return array_slice($matches, 0, $limit);
    }

    public function regionSlugFor(string $placeSlug): ?string
    {
        $place = Location::query()
            ->where('type', LocationKind::Place->value)
            ->where('slug', $placeSlug)
            ->with('parent')
            ->first();

        return $place?->parent?->slug;
    }

    private function fold(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);

        return str_replace(["'", '’', '`', '-', ' '], '', $value);
    }
}

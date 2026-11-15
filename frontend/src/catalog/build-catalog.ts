import { readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

interface LocationNode {
    slug: string;
    name: string;
    type: string;
    children?: LocationNode[];
}

interface AdventureRow {
    slug: string;
    title: string;
    series: string;
    experience_level: string;
    page_count: number | null;
    duration_category: string;
    rule_version: string;
    exotic: boolean;
    complexity: string;
    group_size_min: number | null;
    group_size_max: number | null;
    location_type: string;
    primary_location: string | null;
    primary_region: string | null;
    regions?: string[];
    genres?: string[];
    short_description: string;
    wiki_url: string;
    incomplete?: boolean;
    cover_url?: string | null;
    place_label?: string | null;
    missing_from_wiki?: boolean;
    klappentext?: string | null;
}

export interface CatalogDocument {
    adventures: Array<Record<string, unknown>>;
    places: Array<{ slug: string; name: string; regionSlug: string; regionName: string }>;
    genres: Array<{ slug: string; name: string }>;
    regions: Array<{ slug: string; name: string }>;
}

export function buildCatalog(dataDir: string): CatalogDocument {
    const adventuresFile = JSON.parse(readFileSync(join(dataDir, 'adventures.json'), 'utf8')) as { adventures: AdventureRow[] };
    const seriesFile = JSON.parse(readFileSync(join(dataDir, 'series.json'), 'utf8')) as { series: Array<{ slug: string; name: string }> };
    const tagsFile = JSON.parse(readFileSync(join(dataDir, 'tags.json'), 'utf8')) as { tags: Array<{ slug: string; name: string }> };
    const locationsFile = JSON.parse(readFileSync(join(dataDir, 'locations.json'), 'utf8')) as { locations: LocationNode[] };
    const seriesName = new Map(seriesFile.series.map((series) => [series.slug, series.name]));
    const names = new Map<string, string>();
    const places: CatalogDocument['places'] = [];
    const regions: CatalogDocument['regions'] = [];

    walk(locationsFile.locations, null, names, places, regions);

    const adventures = adventuresFile.adventures
        .filter((row) => row.missing_from_wiki !== true)
        .map((row, index) => ({
            id: index + 1,
            slug: row.slug,
            title: row.title,
            seriesSlug: row.series,
            seriesName: seriesName.get(row.series) ?? row.series,
            experienceLevel: row.experience_level,
            pageCount: row.page_count,
            durationCategory: row.duration_category,
            ruleVersion: row.rule_version,
            exotic: row.exotic,
            complexity: row.complexity,
            groupSizeMin: row.group_size_min,
            groupSizeMax: row.group_size_max,
            locationType: row.location_type,
            primaryPlaceSlug: row.primary_location,
            primaryPlaceName: row.primary_location === null ? null : names.get(row.primary_location) ?? null,
            primaryRegionSlug: row.primary_region,
            regionSlugs: row.regions ?? [],
            genres: row.genres ?? [],
            shortDescription: row.short_description,
            wikiUrl: row.wiki_url,
            incomplete: row.incomplete ?? false,
            coverUrl: row.cover_url ?? null,
            placeLabel: row.place_label ?? null,
            missingFromWiki: false,
            klappentext: row.klappentext ?? null,
        }));

    return {
        adventures,
        places,
        genres: tagsFile.tags,
        regions,
    };
}

export function writeCatalog(dataDir: string, outputPath: string): CatalogDocument {
    const document = buildCatalog(dataDir);

    writeFileSync(outputPath, `${JSON.stringify(document)}\n`);

    return document;
}

function walk(
    nodes: LocationNode[],
    region: { slug: string; name: string } | null,
    names: Map<string, string>,
    places: CatalogDocument['places'],
    regions: CatalogDocument['regions'],
): void {
    for (const node of nodes) {
        names.set(node.slug, node.name);

        if (node.type === 'region') {
            regions.push({ slug: node.slug, name: node.name });
        }

        const nextRegion = node.type === 'region' ? { slug: node.slug, name: node.name } : region;

        if (node.type === 'place' && nextRegion !== null) {
            places.push({
                slug: node.slug,
                name: node.name,
                regionSlug: nextRegion.slug,
                regionName: nextRegion.name,
            });
        }

        walk(node.children ?? [], nextRegion, names, places, regions);
    }
}

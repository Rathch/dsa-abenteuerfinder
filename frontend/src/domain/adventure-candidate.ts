import {
    type Complexity,
    type DurationCategory,
    type ExperienceLevel,
    type LocationType,
    type RegionKey,
    type RuleVersion,
    regionFrom,
    regionIsExtraAventurian,
    REGION_KEYS,
} from './enums';

export interface AdventureCandidateInput {
    id: number;
    slug: string;
    title: string;
    seriesSlug: string;
    seriesName: string;
    experienceLevel: ExperienceLevel;
    pageCount: number | null;
    durationCategory: DurationCategory;
    ruleVersion: RuleVersion;
    exotic: boolean;
    complexity: Complexity;
    groupSizeMin: number | null;
    groupSizeMax: number | null;
    locationType: LocationType;
    primaryPlaceSlug: string | null;
    primaryPlaceName: string | null;
    primaryRegionSlug: string | null;
    regionSlugs: string[];
    genres: string[];
    shortDescription: string;
    wikiUrl: string;
    incomplete?: boolean;
    coverUrl?: string | null;
    placeLabel?: string | null;
    missingFromWiki?: boolean;
    klappentext?: string | null;
}

export class AdventureCandidate {
    readonly incomplete: boolean;

    readonly coverUrl: string | null;

    readonly placeLabel: string | null;

    readonly missingFromWiki: boolean;

    readonly klappentext: string | null;

    constructor(
        readonly id: number,
        readonly slug: string,
        readonly title: string,
        readonly seriesSlug: string,
        readonly seriesName: string,
        readonly experienceLevel: ExperienceLevel,
        readonly pageCount: number | null,
        readonly durationCategory: DurationCategory,
        readonly ruleVersion: RuleVersion,
        readonly exotic: boolean,
        readonly complexity: Complexity,
        readonly groupSizeMin: number | null,
        readonly groupSizeMax: number | null,
        readonly locationType: LocationType,
        readonly primaryPlaceSlug: string | null,
        readonly primaryPlaceName: string | null,
        readonly primaryRegionSlug: string | null,
        readonly regionSlugs: string[],
        readonly genres: string[],
        readonly shortDescription: string,
        readonly wikiUrl: string,
        incomplete = false,
        coverUrl: string | null = null,
        placeLabel: string | null = null,
        missingFromWiki = false,
        klappentext: string | null = null,
    ) {
        this.incomplete = incomplete;
        this.coverUrl = coverUrl;
        this.placeLabel = placeLabel;
        this.missingFromWiki = missingFromWiki;
        this.klappentext = klappentext;
    }

    static fromInput(input: AdventureCandidateInput): AdventureCandidate {
        return new AdventureCandidate(
            input.id,
            input.slug,
            input.title,
            input.seriesSlug,
            input.seriesName,
            input.experienceLevel,
            input.pageCount,
            input.durationCategory,
            input.ruleVersion,
            input.exotic,
            input.complexity,
            input.groupSizeMin,
            input.groupSizeMax,
            input.locationType,
            input.primaryPlaceSlug,
            input.primaryPlaceName,
            input.primaryRegionSlug,
            input.regionSlugs,
            input.genres,
            input.shortDescription,
            input.wikiUrl,
            input.incomplete ?? false,
            input.coverUrl ?? null,
            input.placeLabel ?? null,
            input.missingFromWiki ?? false,
            input.klappentext ?? null,
        );
    }

    allRegionSlugs(): string[] {
        const slugs = [...this.regionSlugs];

        if (this.primaryRegionSlug !== null) {
            slugs.unshift(this.primaryRegionSlug);
        }

        return [...new Set(slugs)];
    }

    matchesPlaceSlug(slug: string): boolean {
        if (this.primaryPlaceSlug === slug) {
            return true;
        }

        return labelContainsPlace(this.placeLabel, slug);
    }

    isExtraAventurian(): boolean {
        return this.allRegionSlugs().some((slug) => {
            const region = regionFrom(slug);

            return region !== null && regionIsExtraAventurian(region);
        });
    }

    displayDescription(): { kind: 'klappentext' | 'kurzbeschreibung'; text: string } | null {
        const klappentext = (this.klappentext ?? '').trim();

        if (klappentext !== '' && klappentext !== '-') {
            return { kind: 'klappentext', text: klappentext };
        }

        const kurzbeschreibung = this.shortDescription.trim();

        if (kurzbeschreibung !== '' && kurzbeschreibung !== '-') {
            return { kind: 'kurzbeschreibung', text: kurzbeschreibung };
        }

        return null;
    }
}

export function regionsMentionedIn(label: string | null, regionByPlaceSlug: Record<string, string>): string[] {
    return regionsForLabel(label, regionIndex(regionByPlaceSlug));
}

export function regionIndex(regionByPlaceSlug: Record<string, string>): Record<string, string[]> {
    const regionsByKey: Record<string, string[]> = {};

    for (const [placeSlug, regionSlug] of Object.entries(regionByPlaceSlug)) {
        pushUnique(regionsByKey, placeSlug, regionSlug);
        const shorter = collisionKey(placeSlug, regionSlug);

        if (shorter !== null && (shorter === regionSlug || Object.prototype.hasOwnProperty.call(regionByPlaceSlug, shorter))) {
            pushUnique(regionsByKey, shorter, regionSlug);
        }
    }

    return regionsByKey;
}

export function regionsForLabel(label: string | null, regionsByMatchKey: Record<string, string[]>): string[] {
    const found: string[] = [];

    for (const key of labelMatchKeys(label)) {
        for (const regionSlug of regionsByMatchKey[key] ?? []) {
            if (!found.includes(regionSlug)) {
                found.push(regionSlug);
            }
        }
    }

    return found;
}

function pushUnique(target: Record<string, string[]>, key: string, value: string): void {
    const list = target[key] ?? [];

    list.push(value);
    target[key] = list;
}

function labelContainsPlace(label: string | null, slug: string): boolean {
    if (label === null || slug === '') {
        return false;
    }

    const keys = [slug];
    const shorter = collisionKey(slug, null);

    if (shorter !== null) {
        keys.push(shorter);
    }

    return labelMatchKeys(label).some((part) => keys.includes(part));
}

function collisionKey(slug: string, regionSlug: string | null): string | null {
    const suffixes = regionSlug !== null ? [regionSlug] : REGION_KEYS;

    for (const suffix of suffixes) {
        const tail = `-${suffix}`;

        if (slug.endsWith(tail) && slug.length > tail.length) {
            return slug.slice(0, -tail.length);
        }
    }

    return null;
}

function labelMatchKeys(label: string | null): string[] {
    if (label === null || label.trim() === '') {
        return [];
    }

    const parts = label.split(/\s*(?:,|;|:|\/|\bund\b)\s*/u);
    const keys: string[] = [];

    for (const part of parts) {
        const key = placeSlugKey(part);

        if (key !== '') {
            keys.push(key);
        }
    }

    return keys;
}

function placeSlugKey(value: string): string {
    let next = value.trim().toLocaleLowerCase('de');
    next = next
        .replaceAll('ä', 'ae')
        .replaceAll('ö', 'oe')
        .replaceAll('ü', 'ue')
        .replaceAll('ß', 'ss')
        .replaceAll('â', 'a')
        .replaceAll('ê', 'e')
        .replaceAll('î', 'i')
        .replaceAll('ô', 'o')
        .replaceAll('û', 'u')
        .replaceAll('á', 'a')
        .replaceAll('é', 'e')
        .replaceAll('í', 'i')
        .replaceAll('ó', 'o')
        .replaceAll('ú', 'u')
        .replaceAll('à', 'a')
        .replaceAll('è', 'e')
        .replaceAll('ì', 'i')
        .replaceAll('ò', 'o')
        .replaceAll('ù', 'u')
        .replaceAll("'", '')
        .replaceAll('’', '')
        .replaceAll('`', '')
        .replaceAll('´', '');
    next = next.replace(/[^a-z0-9]+/gu, '-');

    return next.replace(/^-+|-+$/g, '');
}

import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { buildCatalog } from './build-catalog';

describe('catalog build', () => {
    it('drops adventures missing from the wiki and keeps suggestable ones', () => {
        const dir = mkdtempSync(join(tmpdir(), 'catalog-'));

        writeFileSync(join(dir, 'adventures.json'), JSON.stringify({
            adventures: [
                adventure('sichtbar', false),
                adventure('fehlt', true),
            ],
        }));
        writeFileSync(join(dir, 'series.json'), JSON.stringify({
            series: [{ slug: 'va-reihe', name: 'VA-Reihe' }],
        }));
        writeFileSync(join(dir, 'tags.json'), JSON.stringify({
            tags: [{ slug: 'investigation', name: 'Ermittlung' }],
        }));
        writeFileSync(join(dir, 'locations.json'), JSON.stringify({
            locations: [{
                slug: 'aventurien',
                name: 'Aventurien',
                type: 'continent',
                children: [{
                    slug: 'nordmarken',
                    name: 'Nordmarken',
                    type: 'region',
                    children: [{ slug: 'havena', name: 'Havena', type: 'place' }],
                }],
            }],
        }));

        const catalog = buildCatalog(dir);
        const slugs = catalog.adventures.map((entry) => entry.slug);

        expect(slugs).toContain('sichtbar');
        expect(slugs).not.toContain('fehlt');
        expect(catalog.adventures[0]?.primaryPlaceName).toBe('Havena');
        expect(catalog.places[0]?.regionName).toBe('Nordmarken');
    });
});

function adventure(slug: string, missing: boolean): Record<string, unknown> {
    return {
        slug,
        title: slug,
        series: 'va-reihe',
        experience_level: 'einsteiger',
        page_count: 32,
        duration_category: 'kurz',
        rule_version: 'dsa5',
        exotic: false,
        complexity: 'niedrig',
        group_size_min: null,
        group_size_max: null,
        location_type: 'point',
        primary_location: 'havena',
        primary_region: 'nordmarken',
        regions: ['nordmarken'],
        genres: ['investigation'],
        short_description: 'Kurz',
        wiki_url: 'https://de.wiki-aventurica.de/wiki/Test',
        missing_from_wiki: missing,
    };
}

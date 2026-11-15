import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const root = resolve(import.meta.dirname, '../../..');

describe('catalog load', () => {
    it('does not block the first html on the catalog', () => {
        const html = readFileSync(resolve(root, 'frontend/index.html'), 'utf8');
        const boot = readFileSync(resolve(root, 'frontend/src/boot.ts'), 'utf8');

        expect(html.includes('catalog.json')).toBe(false);
        expect(boot.indexOf('fetch')).toBeGreaterThan(boot.indexOf('DOMContentLoaded'));
    });
});

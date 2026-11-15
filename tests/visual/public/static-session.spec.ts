// npx playwright test --config=playwright.static.config.ts
import { expect, test } from '@playwright/test';
import { completeWizard, formHeading } from '../wizard-helpers';

test('keeps wizard answers on the device and ranks without another document request', async ({ page }) => {
    const documents: string[] = [];

    page.on('request', (request) => {
        if (request.resourceType() === 'document') {
            documents.push(request.url());
        }
    });

    await page.goto('/');
    await page.waitForResponse((response) => response.url().includes('/catalog.json') && response.ok());
    await expect(formHeading(page)).toBeVisible();
    documents.length = 0;

    await completeWizard(page);
    await expect(page.getByRole('heading', { name: 'Deine Empfehlungen' })).toBeVisible();
    expect(documents).toEqual([]);

    await page.reload();
    await expect(page.getByRole('heading', { name: 'Deine Empfehlungen' })).toBeVisible();
    await expect.poll(async () => page.evaluate(() => localStorage.getItem('adventure_wizard'))).not.toBeNull();

    const themeBefore = await page.evaluate(() => {
        localStorage.setItem('theme', 'dark');

        return localStorage.getItem('theme');
    });

    await page.getByRole('button', { name: 'Neu starten' }).click();
    await expect(formHeading(page)).toBeVisible();
    await expect.poll(async () => page.evaluate(() => localStorage.getItem('adventure_wizard'))).toBeNull();
    expect(themeBefore).toBe('dark');
    await expect.poll(async () => page.evaluate(() => localStorage.getItem('theme'))).toBe('dark');
});

test('shows the legal pages and no admin form', async ({ page }) => {
    await page.goto('/');
    await expect(formHeading(page)).toBeVisible();

    await page.goto('/impressum/');
    await expect(page.getByRole('heading', { level: 1, name: 'Impressum' })).toBeVisible();

    await page.goto('/datenschutz/');
    await expect(page.getByRole('heading', { level: 1, name: 'Datenschutz' })).toBeVisible();

    await page.goto('/verwaltung');
    await expect(page.getByRole('heading', { level: 1, name: 'Seite nicht gefunden' })).toBeVisible();
    await expect(page.locator('form')).toHaveCount(0);
});

test('describes local answers and no admin session on the privacy page', async ({ page }) => {
    await page.goto('/datenschutz/');
    const text = await page.locator('main').innerText();

    expect(text).toContain('bleiben auf deinem Gerät');
    expect(text).toContain('nicht an den Host gesendet');
    expect(text).not.toContain('Verwaltung');
    expect(text).not.toContain('Passwort');
    expect(text).not.toContain('CSRF');
});

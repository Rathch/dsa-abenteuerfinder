import { expect, test } from '@playwright/test';
import { setTheme } from '../a11y-helpers';
import { formHeading } from '../wizard-helpers';

test.describe('Tastatur-Leitplanken', () => {
    test('Skip-Link ist erster Tab-Stopp, sichtbar und springt zum Hauptinhalt', async ({ page }) => {
        await page.goto('/');

        await page.keyboard.press('Tab');
        const skipLink = page.getByRole('link', { name: 'Zum Hauptinhalt springen' });
        await expect(skipLink).toBeFocused();
        await expect(skipLink).toBeInViewport({ ratio: 1 });

        const box = await skipLink.boundingBox();
        expect(box).not.toBeNull();
        expect(box!.y).toBeGreaterThanOrEqual(0);

        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(/#main-content$/);
        await expect(page.locator('main#main-content')).toBeFocused();

        // Der nächste Tab-Stopp liegt im Hauptinhalt: erste Regelwerk-Option.
        await page.keyboard.press('Tab');
        await expect(page.locator('#ruleset-0')).toBeFocused();
    });

    test('Skip-Link ist ohne Fokus nicht im Sichtbereich', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByRole('link', { name: 'Zum Hauptinhalt springen' })).not.toBeInViewport();
    });

    for (const theme of ['light', 'dark'] as const) {
        test(`Fokus ist bei den Formular-Steuerelementen sichtbar (${theme})`, async ({ page }) => {
            await setTheme(page, theme);
            await page.goto('/');
            await expect(formHeading(page)).toBeVisible();

            await page.locator('#ruleset-0').focus();
            const radio = page.locator('#ruleset-0');
            await expect(radio).toBeFocused();

            // Tastaturfokus: die umgebende Antwortkarte zeigt einen sichtbaren Ring (Outline > 0).
            const outline = await page.locator('label.choice', { has: radio }).evaluate((element) => {
                const style = getComputedStyle(element);
                return { width: parseFloat(style.outlineWidth), style: style.outlineStyle };
            });
            expect(outline.style).not.toBe('none');
            expect(outline.width).toBeGreaterThanOrEqual(2);

            const themeToggle = page.getByRole('switch', { name: 'Dunkles Design' });
            await themeToggle.focus();
            await expect(themeToggle).toBeFocused();
            const toggleOutline = await themeToggle.evaluate((element) => {
                const style = getComputedStyle(element);
                return { width: parseFloat(style.outlineWidth), style: style.outlineStyle };
            });
            expect(toggleOutline.style).not.toBe('none');
            expect(toggleOutline.width).toBeGreaterThanOrEqual(2);
        });
    }

    test('Theme-Umschalter ist per Tastatur bedienbar und folgt dem Browser-Standard', async ({ page }) => {
        await page.emulateMedia({ colorScheme: 'light' });
        await page.goto('/');
        // Einmalig leeren (nicht per addInitScript: das würde auch den Reload wieder löschen).
        await page.evaluate(() => localStorage.removeItem('theme'));
        await page.reload();

        const toggle = page.getByRole('switch', { name: 'Dunkles Design' });
        await expect(toggle).toHaveAttribute('aria-checked', 'false');
        await expect(page.locator('html')).not.toHaveClass(/dark/);

        await toggle.focus();
        await page.keyboard.press('Enter');
        await expect(toggle).toHaveAttribute('aria-checked', 'true');
        await expect(page.locator('html')).toHaveClass(/dark/);
        expect(await page.evaluate(() => localStorage.getItem('theme'))).toBe('dark');

        await page.keyboard.press('Space');
        await expect(toggle).toHaveAttribute('aria-checked', 'false');
        await expect(page.locator('html')).not.toHaveClass(/dark/);
        expect(await page.evaluate(() => localStorage.getItem('theme'))).toBe('light');

        // Gespeicherte Wahl bleibt nach dem Neuladen erhalten.
        await page.keyboard.press('Enter');
        await expect(toggle).toHaveAttribute('aria-checked', 'true');
        expect(await page.evaluate(() => localStorage.getItem('theme'))).toBe('dark');
        await page.reload();
        await expect(page.locator('html')).toHaveClass(/dark/);
        await expect(page.getByRole('switch', { name: 'Dunkles Design' })).toHaveAttribute('aria-checked', 'true');
    });

    test('Ohne gespeicherte Wahl gilt der Browser-Standard', async ({ page }) => {
        await page.emulateMedia({ colorScheme: 'dark' });
        await page.goto('/');
        await page.evaluate(() => localStorage.removeItem('theme'));
        await page.reload();

        await expect(page.locator('html')).toHaveClass(/dark/);
        await expect(page.getByRole('switch', { name: 'Dunkles Design' })).toHaveAttribute('aria-checked', 'true');
    });

    test('Fußzeile verlinkt Impressum und Datenschutz', async ({ page }) => {
        await page.goto('/');
        const footer = page.getByRole('contentinfo');
        await expect(footer.getByRole('link', { name: 'Impressum' })).toHaveAttribute('href', /\/impressum$/);
        await expect(footer.getByRole('link', { name: 'Datenschutz' })).toHaveAttribute('href', /\/datenschutz$/);
    });
});

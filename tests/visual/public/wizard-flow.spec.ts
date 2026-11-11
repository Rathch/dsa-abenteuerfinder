import { expect, test } from '@playwright/test';
import {
    chooseChecks,
    chooseRadioInFieldsetAndPersist,
    completeWizard,
    formHeading,
    resultsHeading,
    reviewHeading,
} from '../wizard-helpers';

test.describe('Wunsch-Formular', () => {
    test('führt mit Mausbedienung zu den Empfehlungen @mobile', async ({ page }) => {
        await page.goto('/');
        await expect(formHeading(page)).toBeVisible();

        await completeWizard(page);

        const cards = page.getByTestId('recommendation');
        await expect(cards.first()).toBeVisible();
        expect(await cards.count()).toBeGreaterThanOrEqual(3);
        expect(await cards.count()).toBeLessThanOrEqual(6);

        const first = cards.first();
        await expect(first.getByRole('heading', { level: 3 })).toBeVisible();
        await expect(first.getByText('Warum passt es?')).toBeVisible();
        await expect(first.getByText(/^Reihe:/)).toBeVisible();

        const external = first.getByRole('link', { name: /öffnet in neuem Tab/ });
        expect(await external.count()).toBeGreaterThanOrEqual(1);
        for (const link of await external.all()) {
            await expect(link).toHaveAttribute('target', '_blank');
            await expect(link).toHaveAttribute('rel', 'noopener noreferrer');
        }
        await expect(first.getByRole('link', { name: /Wiki Aventurica/ })).toHaveAttribute(
            'href',
            /wiki-aventurica\.de/,
        );
    });

    test('lässt sich komplett per Tastatur bedienen', async ({ page }) => {
        await page.goto('/');
        await expect(formHeading(page)).toBeVisible();

        await page.locator('#ruleset-0').focus();
        await page.keyboard.press('Space');
        await page.getByRole('button', { name: 'Weiter', exact: true }).focus();
        await page.keyboard.press('Enter');
        await expect(page.getByRole('group', { name: /Heldinnen und Helden/ })).toBeVisible();

        await page.locator('#experienceLevel-0').focus();
        await page.keyboard.press('Space');
        await page.getByRole('button', { name: 'Weiter', exact: true }).focus();
        await page.keyboard.press('Enter');
        await expect(page.getByRole('group', { name: /exotisch/ })).toBeVisible();

        await page.locator('#exotic-0').focus();
        await page.keyboard.press('Space');
        await page.getByRole('button', { name: 'Weiter', exact: true }).focus();
        await page.keyboard.press('Enter');
        await expect(page.getByRole('group', { name: /Region/ })).toBeVisible();

        await page.locator('#regionSlug-1').focus();
        await page.keyboard.press('Space');
        await expect(page.getByRole('radio', { name: 'Havena' })).toBeVisible();
        await page.locator('#place-havena').focus();
        await page.keyboard.press('Space');
        await expect(page.locator('#place-havena')).toBeChecked();

        await page.getByRole('button', { name: 'Weiter', exact: true }).focus();
        await page.keyboard.press('Enter');
        await expect(page.getByRole('group', { name: /Zeit/ })).toBeVisible();

        await page.locator('#timeframe-0').focus();
        await page.keyboard.press('Space');
        await page.getByRole('button', { name: 'Weiter', exact: true }).focus();
        await page.keyboard.press('Enter');
        await expect(page.getByRole('group', { name: /Genres/ })).toBeVisible();

        const firstGenre = page.locator('fieldset input[type="checkbox"]').first();
        await firstGenre.focus();
        await page.keyboard.press('Space');
        await expect(firstGenre).toBeChecked();
        await page.getByRole('button', { name: 'Weiter', exact: true }).focus();
        await page.keyboard.press('Enter');

        await page.locator('#gmExperience-0').focus();
        await page.keyboard.press('Space');
        await page.getByRole('button', { name: 'Weiter', exact: true }).focus();
        await page.keyboard.press('Enter');
        await expect(page.getByRole('group', { name: /Spielende/ })).toBeVisible();

        await page.locator('#groupSize-0').focus();
        await page.keyboard.press('Space');
        await page.getByRole('button', { name: 'Weiter', exact: true }).focus();
        await page.keyboard.press('Enter');
        await expect(reviewHeading(page)).toBeVisible();

        await page.getByRole('button', { name: 'Empfehlungen anzeigen' }).focus();
        await page.keyboard.press('Enter');
        await expect(resultsHeading(page)).toBeFocused();
        await expect(page.getByTestId('recommendation').first()).toBeVisible();
    });

    test('vergrößert den Umschlag an Ort und Stelle und verkleinert ihn wieder', async ({ page }) => {
        await page.goto('/');
        await completeWizard(page);

        await expect(page.locator('button:has(img[src*="cover-fallback.svg"])')).toHaveCount(0);

        const fallbacks = page.locator('img[src*="cover-fallback.svg"]');
        const fallback = fallbacks.first();
        await expect(fallback).toBeVisible();
        const fallbackBefore = await fallback.boundingBox();
        expect(fallbackBefore).not.toBeNull();
        await fallback.click({ force: true });
        const fallbackAfter = await fallback.boundingBox();
        expect(fallbackAfter).not.toBeNull();
        expect(Math.abs((fallbackAfter?.width ?? 0) - (fallbackBefore?.width ?? 0))).toBeLessThan(2);
        expect(Math.abs((fallbackAfter?.height ?? 0) - (fallbackBefore?.height ?? 0))).toBeLessThan(2);

        const covers = page.locator('[data-cover-preview]');
        if ((await covers.count()) === 0) {
            return;
        }

        const cover = covers.first();
        const card = page.getByTestId('recommendation').filter({ has: cover });
        const image = cover.locator('img');

        await expect(cover).toHaveAttribute('aria-expanded', 'false');
        await expect(page.locator('dialog')).toHaveCount(0);
        await image.evaluate(
            (node: HTMLImageElement) =>
                new Promise<void>((resolve, reject) => {
                    if (node.complete) {
                        if (node.naturalWidth > 0) {
                            resolve();
                        } else {
                            reject(new Error('Umschlagbild lädt nicht'));
                        }

                        return;
                    }

                    node.addEventListener('load', () => resolve(), { once: true });
                    node.addEventListener('error', () => reject(new Error('Umschlagbild lädt nicht')), { once: true });
                }),
        );

        const collapsed = await image.boundingBox();
        expect(collapsed).not.toBeNull();

        await cover.click();
        await page.mouse.move(0, 0);
        await expect(cover).toHaveAttribute('aria-expanded', 'true');
        await expect(cover).toBeFocused();
        await expect(card).toBeVisible();
        for (let index = 1; index < await covers.count(); index++) {
            await expect(covers.nth(index)).toHaveAttribute('aria-expanded', 'false');
        }
        const enlarged = await image.boundingBox();
        expect(enlarged).not.toBeNull();
        expect(enlarged!.width).toBeGreaterThan((collapsed?.width ?? 0) + 20);

        const naturalWidth = await image.evaluate((node: HTMLImageElement) => node.naturalWidth);
        expect(enlarged!.width).toBeLessThanOrEqual(Math.min(naturalWidth, 288) + 1);
        expect(enlarged!.height).toBeLessThanOrEqual(384 + 2);
        await expect(image).toHaveCSS('clip-path', 'none');

        await cover.click();
        await page.mouse.move(0, 0);
        await expect(cover).toHaveAttribute('aria-expanded', 'false');
        await expect(cover).toBeFocused();

        const restored = await image.boundingBox();
        expect(restored).not.toBeNull();
        expect(restored!.width).toBeLessThan(enlarged!.width - 20);

        await cover.focus();
        await page.keyboard.press('Enter');
        await expect(cover).toHaveAttribute('aria-expanded', 'true');
        await expect(cover).toBeFocused();
        await page.keyboard.press('Space');
        await expect(cover).toHaveAttribute('aria-expanded', 'false');
        await expect(cover).toBeFocused();
    });

    test('zeigt auf der ersten Frage noch keinen Submit-Button', async ({ page }) => {
        await page.goto('/');
        await expect(formHeading(page)).toBeVisible();
        await expect(page.getByRole('button', { name: 'Empfehlungen anzeigen' })).toHaveCount(0);
        await expect(page.getByRole('group', { name: /Regelwerk/ })).toBeVisible();
    });

    test('zeigt einen Fehler, wenn die Region fehlt, und fokussiert die Fehlerübersicht', async ({ page }) => {
        await page.goto('/');
        await chooseChecks(page, /Regelwerk/, ['DSA 5']);
        await chooseRadioInFieldsetAndPersist(page, /Heldinnen und Helden/, 'Einsteiger');
        await chooseChecks(page, /exotisch/, ['Nur Aventurien']);
        await expect(page.getByRole('group', { name: /Region/ })).toBeVisible();
        await page.getByRole('button', { name: 'Weiter', exact: true }).click();

        const summary = page.locator('#error-summary');
        await expect(summary).toBeVisible();
        await expect(summary).toBeFocused();
        await expect(summary).toContainText('Region');
    });

    test('Antworten bleiben nach Neuladen in der Zusammenfassung erhalten', async ({ page }) => {
        await page.goto('/');
        await chooseChecks(page, /Regelwerk/, ['DSA 5']);
        await chooseRadioInFieldsetAndPersist(page, /Heldinnen und Helden/, 'Erfahrene');
        await page.reload();
        await expect(formHeading(page)).toBeVisible();
        await expect(page.getByRole('button', { name: /Helden:.*Erfahrene/ })).toBeVisible();
        await expect(page.getByRole('group', { name: /exotisch/ })).toBeVisible();
    });

    test('Neu starten auf der Ergebnisseite öffnet wieder das Formular', async ({ page }) => {
        await page.goto('/');
        await completeWizard(page);

        await page.getByRole('button', { name: 'Neu starten' }).click();
        await expect(formHeading(page)).toBeVisible();
        await expect(page.getByRole('group', { name: /Regelwerk/ })).toBeVisible();
        await expect(page.getByRole('button', { name: /Helden:/ })).toHaveCount(0);
    });
});

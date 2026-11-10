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

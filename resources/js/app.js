/**
 * Theme-Umschalter:
 * - Standard: Browser-Einstellung (prefers-color-scheme), solange keine eigene Wahl gespeichert ist.
 * - Nach Klick: light/dark in localStorage; der Schalter zeigt den Zustand „Dunkles Design“ an/aus.
 */
const root = document.documentElement;
const THEME_KEY = 'theme';
const media = window.matchMedia('(prefers-color-scheme: dark)');

function systemTheme() {
    return media.matches ? 'dark' : 'light';
}

function storedTheme() {
    try {
        const value = localStorage.getItem(THEME_KEY);
        return value === 'light' || value === 'dark' ? value : null;
    } catch (error) {
        return null;
    }
}

function currentTheme() {
    return root.classList.contains('dark') ? 'dark' : 'light';
}

function syncToggles() {
    const isDark = currentTheme() === 'dark';

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-checked', isDark ? 'true' : 'false');
        button.setAttribute('aria-label', 'Dunkles Design');
        button.classList.toggle('is-on', isDark);
    });
}

function applyTheme(theme, { persist = false } = {}) {
    root.classList.toggle('dark', theme === 'dark');
    root.dataset.theme = theme;
    root.style.colorScheme = theme;

    if (persist) {
        try {
            localStorage.setItem(THEME_KEY, theme);
        } catch (error) {
            // Speicher nicht verfügbar: Umschaltung gilt nur für diese Seite.
        }
    }

    syncToggles();
}

function resolveTheme() {
    return storedTheme() ?? systemTheme();
}

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target.closest('[data-theme-toggle]') : null;
    if (!target) {
        return;
    }

    applyTheme(currentTheme() === 'dark' ? 'light' : 'dark', { persist: true });
});

// Browser-Standard nachziehen, solange der Nutzer nichts gespeichert hat.
const onSystemThemeChange = () => {
    if (storedTheme() === null) {
        applyTheme(systemTheme());
    }
};

if (typeof media.addEventListener === 'function') {
    media.addEventListener('change', onSystemThemeChange);
} else if (typeof media.addListener === 'function') {
    media.addListener(onSystemThemeChange);
}

applyTheme(resolveTheme());

/**
 * Fokusführung für den Wizard: Livewire sendet `wizard-focus` mit der ID des Ziels
 * (Formularüberschrift, Fehlerübersicht, Ergebnisüberschrift, Ortsfeld).
 */
function focusTarget(id) {
    if (!id) {
        return;
    }

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            const element = document.getElementById(id);
            if (element) {
                element.focus({ preventScroll: false });
            }
        });
    });
}

let wizardFocusRegistered = false;

function registerWizardFocus() {
    if (wizardFocusRegistered || !window.Livewire) {
        return;
    }

    wizardFocusRegistered = true;
    window.Livewire.on('wizard-focus', (payload) => {
        const detail = Array.isArray(payload) ? payload[0] : payload;
        focusTarget(detail && detail.target);
    });
}

document.addEventListener('livewire:init', registerWizardFocus);
registerWizardFocus();

/**
 * Das gezeigte Tortenstück nach vorn holen, damit die Vergrößerung nicht unter den Nachbarstücken liegt.
 */
document.addEventListener('pointerover', (event) => {
    const slice = event.target instanceof Element ? event.target.closest('.pie-slice') : null;
    const parent = slice?.parentElement;

    if (!slice || !parent || parent.lastElementChild === slice) {
        return;
    }

    parent.appendChild(slice);
});

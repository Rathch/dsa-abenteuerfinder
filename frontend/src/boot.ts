import { mountWizard, type PublicCatalog } from './wizard';

document.addEventListener('click', (event) => {
    const target = event.target;
    const button = target instanceof Element ? target.closest<HTMLButtonElement>('[data-cover-preview]') : null;

    if (!button) {
        return;
    }

    const expanded = button.getAttribute('aria-expanded') === 'true';
    const image = button.querySelector('img');
    button.setAttribute('aria-expanded', expanded ? 'false' : 'true');

    if (!(image instanceof HTMLImageElement)) {
        return;
    }

    if (expanded || image.naturalWidth === 0) {
        image.style.width = '';
        image.style.height = '';

        return;
    }

    const scale = Math.min(1.5, (27 * 16) / image.naturalWidth, (36 * 16) / image.naturalHeight);
    image.style.width = `${Math.round(image.naturalWidth * scale)}px`;
    image.style.height = `${Math.round(image.naturalHeight * scale)}px`;
});

document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector<HTMLButtonElement>('[data-theme-toggle]');

    if (toggle) {
        toggle.setAttribute('aria-checked', document.documentElement.classList.contains('dark') ? 'true' : 'false');
    }

    toggle?.addEventListener('click', () => {
        const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        localStorage.setItem('theme', next);
        document.documentElement.classList.toggle('dark', next === 'dark');
        document.documentElement.dataset.theme = next;
        document.documentElement.style.colorScheme = next;
        toggle.setAttribute('aria-checked', next === 'dark' ? 'true' : 'false');
    });

    const root = document.querySelector<HTMLElement>('#abenteuer-finden');

    if (root === null) {
        return;
    }

    const catalogUrl = new URL('catalog.json', new URL('..', import.meta.url));

    void fetch(catalogUrl)
        .then((response) => response.json())
        .then((documentJson: PublicCatalog) => {
            mountWizard(root, documentJson);
        });
});

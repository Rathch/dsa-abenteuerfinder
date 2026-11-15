import { mountWizard, type PublicCatalog } from './wizard';

document.addEventListener('click', (event) => {
    const target = event.target;
    const button = target instanceof Element ? target.closest<HTMLButtonElement>('[data-cover-preview]') : null;

    if (!button) {
        return;
    }

    const expanded = button.getAttribute('aria-expanded') === 'true';
    button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
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

    void fetch('/catalog.json')
        .then((response) => response.json())
        .then((documentJson: PublicCatalog) => {
            mountWizard(root, documentJson);
        });
});

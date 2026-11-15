(function () {
    try {
        var stored = localStorage.getItem('theme');
        var theme = stored === 'light' || stored === 'dark'
            ? stored
            : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.classList.toggle('dark', theme === 'dark');
        document.documentElement.dataset.theme = theme;
        document.documentElement.style.colorScheme = theme;
    } catch (e) {
        // localStorage can be blocked; the page still follows the browser default.
    }
})();

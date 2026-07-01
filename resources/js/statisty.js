document.addEventListener('DOMContentLoaded', function () {
    console.debug('Statisty assets loaded');

    const storageKey = 'statistyTheme';
    const root = document.documentElement;
    const swatches = Array.from(document.querySelectorAll('.statisty-theme-swatch'));

    function activateSwatch(button) {
        swatches.forEach(function (swatch) {
            swatch.classList.toggle('active', swatch === button);
        });
    }

    function applyTheme(theme) {
        if (!theme || !theme.primary) {
            return;
        }

        root.style.setProperty('--color-primary', theme.primary);
        root.style.setProperty('--color-primary-hover', theme.hover || theme.primary);
        if (theme.secondary) {
            root.style.setProperty('--color-secondary', theme.secondary);
        }
    }

    function loadTheme() {
        const stored = localStorage.getItem(storageKey);
        if (!stored) {
            return;
        }

        try {
            const theme = JSON.parse(stored);
            applyTheme(theme);
            const matchingSwatch = swatches.find(function (swatch) {
                return swatch.dataset.theme === theme.name || swatch.dataset.primary === theme.primary;
            });
            if (matchingSwatch) {
                activateSwatch(matchingSwatch);
            }
        } catch (error) {
            console.warn('Statisty: impossible de restaurer le thème', error);
        }
    }

    swatches.forEach(function (button) {
        button.addEventListener('click', function () {
            const theme = {
                name: button.dataset.theme,
                primary: button.dataset.primary,
                hover: button.dataset.hover,
                secondary: button.dataset.secondary,
            };

            applyTheme(theme);
            activateSwatch(button);
            localStorage.setItem(storageKey, JSON.stringify(theme));
        });
    });

    loadTheme();
});

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

    // ── Recherche rapide de workflow (topbar) ──────────────────────────────
    const searchInput = document.getElementById('statistyTopbarSearch');
    const resultsBox = document.getElementById('statistyTopbarSearchResults');

    if (searchInput && resultsBox) {
        let workflows = [];
        try {
            workflows = JSON.parse(searchInput.dataset.workflows || '[]');
        } catch (error) {
            workflows = [];
        }

        function renderResults(matches) {
            resultsBox.innerHTML = '';

            if (matches.length === 0) {
                resultsBox.classList.remove('open');
                return;
            }

            matches.slice(0, 8).forEach(function (workflow) {
                const item = document.createElement('a');
                item.href = workflow.url;
                item.className = 'statisty-topbar-search-item';
                item.textContent = workflow.label;
                resultsBox.appendChild(item);
            });

            resultsBox.classList.add('open');
        }

        searchInput.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();

            if (query === '') {
                resultsBox.classList.remove('open');
                resultsBox.innerHTML = '';
                return;
            }

            const matches = workflows.filter(function (workflow) {
                return (workflow.label || '').toLowerCase().includes(query)
                    || (workflow.class || '').toLowerCase().includes(query);
            });

            renderResults(matches);
        });

        searchInput.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') {
                return;
            }

            const query = this.value.toLowerCase().trim();
            if (query === '') {
                return;
            }

            const match = workflows.find(function (workflow) {
                return (workflow.label || '').toLowerCase().includes(query);
            });

            if (match) {
                window.location.href = match.url;
            }
        });

        document.addEventListener('click', function (event) {
            if (!resultsBox.contains(event.target) && event.target !== searchInput) {
                resultsBox.classList.remove('open');
            }
        });
    }
});
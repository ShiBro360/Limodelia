(function () {
    var storageKey = 'limodelia-theme';
    var root = document.documentElement;

    function applyTheme(theme) {
        root.setAttribute('data-theme', theme);
        applyPreviewPalette(theme);
        document.body.classList.add('theme-transition');
        window.setTimeout(function () {
            document.body.classList.remove('theme-transition');
        }, 220);
    }

    function labelFor(theme) {
        return theme === 'dark' ? 'Tryb ciemny' : 'Tryb jasny';
    }

    function applyPreviewPalette(theme) {
        var roleVariables = {
            background: '--color-bg',
            surface: '--color-card',
            primary: '--color-primary',
            accent: '--color-accent'
        };

        document.querySelectorAll('[data-theme-color="' + theme + '"]').forEach(function (input) {
            var cssVariable = roleVariables[input.getAttribute('data-theme-role')];
            if (cssVariable) {
                root.style.setProperty(cssVariable, input.value);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var savedTheme = window.localStorage.getItem(storageKey) || root.getAttribute('data-default-theme') || 'light';
        var toggle = document.querySelector('[data-theme-toggle]');
        var settingsForm = document.querySelector('[data-theme-settings-form]');

        applyTheme(savedTheme);

        if (settingsForm) {
            settingsForm.querySelectorAll('[data-theme-color]').forEach(function (input) {
                input.addEventListener('input', function () {
                    var output = input.closest('.settings-color-value').querySelector('output');
                    if (output) {
                        output.value = input.value.toUpperCase();
                    }
                    if (root.getAttribute('data-theme') === input.getAttribute('data-theme-color')) {
                        applyPreviewPalette(root.getAttribute('data-theme'));
                    }
                });
            });

            settingsForm.querySelectorAll('input[name="default_theme"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    settingsForm.querySelectorAll('[data-theme-card]').forEach(function (card) {
                        card.classList.toggle('is-active', card.getAttribute('data-theme-card') === radio.value);
                    });
                    window.localStorage.setItem(storageKey, radio.value);
                    applyTheme(radio.value);
                    if (toggle) {
                        toggle.querySelector('[data-theme-label]').textContent = labelFor(radio.value);
                        toggle.setAttribute('aria-pressed', radio.value === 'dark' ? 'true' : 'false');
                    }
                });
            });

            settingsForm.addEventListener('submit', function () {
                var selectedTheme = settingsForm.querySelector('input[name="default_theme"]:checked');
                if (selectedTheme) {
                    window.localStorage.setItem(storageKey, selectedTheme.value);
                }
            });
        }

        if (!toggle) {
            return;
        }

        toggle.querySelector('[data-theme-label]').textContent = labelFor(savedTheme);
        toggle.setAttribute('aria-pressed', savedTheme === 'dark' ? 'true' : 'false');

        toggle.addEventListener('click', function () {
            var nextTheme = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            window.localStorage.setItem(storageKey, nextTheme);
            applyTheme(nextTheme);
            toggle.querySelector('[data-theme-label]').textContent = labelFor(nextTheme);
            toggle.setAttribute('aria-pressed', nextTheme === 'dark' ? 'true' : 'false');
        });
    });
})();

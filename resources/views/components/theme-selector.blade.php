@php
    $id = 'theme-selector-' . uniqid();
@endphp

<button 
    type="button"
    class="btn btn-ghost btn-square theme-selector"
    title="Toggle Theme"
    data-theme-selector="{{ $id }}"
>
    <x-tabler-brightness-2 class="theme-icon theme-light size-5" style="display: none !important;" />
    <x-tabler-brightness-auto class="theme-icon theme-auto size-5" style="display: none !important;" />
    <x-tabler-brightness-half class="theme-icon theme-dark size-5" style="display: none !important;" />
</button>

<script>
    (function() {
        // Initialize global theme state if not exists
        window.globalThemeState = window.globalThemeState || {
            currentTheme: localStorage.getItem('theme') || 'auto',
            isApplying: false
        };

        // Wait for DOM to be ready
        function initThemeSelector() {
            // Create isolated scope for this theme selector
            const button = document.querySelector('[data-theme-selector="{{ $id }}"]');
            if (!button) return;

            const lightIcon = button.querySelector('.theme-light');
            const autoIcon = button.querySelector('.theme-auto');
            const darkIcon = button.querySelector('.theme-dark');

        function updateIcon() {
            // Hide all icons in this selector
            lightIcon.style.cssText = 'display: none !important;';
            autoIcon.style.cssText = 'display: none !important;';
            darkIcon.style.cssText = 'display: none !important;';

            // Show current theme icon
            switch(window.globalThemeState.currentTheme) {
                case 'light':
                    lightIcon.style.cssText = 'display: block !important;';
                    break;
                case 'auto':
                    autoIcon.style.cssText = 'display: block !important;';
                    break;
                case 'dark':
                    darkIcon.style.cssText = 'display: block !important;';
                    break;
            }
        }

        function applyTheme() {
            if (window.globalThemeState.isApplying) return;
            window.globalThemeState.isApplying = true;
            
            let isDark;
            if (window.globalThemeState.currentTheme === 'auto') {
                isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            } else {
                isDark = window.globalThemeState.currentTheme === 'dark';
            }
            
            const actualTheme = isDark ? 'corvmc-dark' : 'corvmc';
            document.documentElement.setAttribute('data-theme', actualTheme);
            document.documentElement.style.setProperty('color-scheme', isDark ? 'dark' : 'light');
            document.documentElement.classList.toggle('dark', isDark);
            
            // Update all theme selector icons on the page
            updateAllThemeSelectors();
            window.globalThemeState.isApplying = false;
        }

        function cycleTheme() {
            const themes = ['light', 'auto', 'dark'];
            const currentIndex = themes.indexOf(window.globalThemeState.currentTheme);
            window.globalThemeState.currentTheme = themes[(currentIndex + 1) % themes.length];
            localStorage.setItem('theme', window.globalThemeState.currentTheme);
            applyTheme();
        }

            // Add click handler
            button.addEventListener('click', cycleTheme);

            // Initialize
            updateIcon();
        }

        // Run immediately if DOM is ready, otherwise wait
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initThemeSelector);
        } else {
            initThemeSelector();
        }

        // Global function for syncing all selectors
        window.updateAllThemeSelectors = window.updateAllThemeSelectors || function() {
            document.querySelectorAll('.theme-selector').forEach(selector => {
                const light = selector.querySelector('.theme-light');
                const auto = selector.querySelector('.theme-auto');
                const dark = selector.querySelector('.theme-dark');

                light.style.cssText = 'display: none !important;';
                auto.style.cssText = 'display: none !important;';
                dark.style.cssText = 'display: none !important;';

                switch(window.globalThemeState.currentTheme) {
                    case 'light':
                        light.style.cssText = 'display: block !important;';
                        break;
                    case 'auto':
                        auto.style.cssText = 'display: block !important;';
                        break;
                    case 'dark':
                        dark.style.cssText = 'display: block !important;';
                        break;
                }
            });
        };

        // Listen for system theme changes when in auto mode (only set up once)
        if (!window.systemThemeListener) {
            window.systemThemeListener = true;
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
                if (window.globalThemeState.currentTheme === 'auto') {
                    applyTheme();
                }
            });
        }
    })();
</script>
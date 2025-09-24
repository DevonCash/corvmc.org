@php
    $id = 'theme-selector-' . uniqid();
@endphp

<button 
    type="button"
    class="btn btn-ghost btn-square theme-selector"
    title="Toggle Theme"
    data-theme-selector="{{ $id }}"
>
    <x-tabler-brightness-2 class="theme-icon theme-light size-5" style="display: none;" />
    <x-tabler-brightness-auto class="theme-icon theme-auto size-5" style="display: none;" />
    <x-tabler-brightness-half class="theme-icon theme-dark size-5" style="display: none;" />
</button>

<script>
    (function() {
        // Initialize global theme state if not exists
        window.globalThemeState = window.globalThemeState || {
            currentTheme: localStorage.getItem('theme') || 'auto',
            isApplying: false
        };

        // Create isolated scope for this theme selector
        const button = document.querySelector('[data-theme-selector="{{ $id }}"]');
        const lightIcon = button.querySelector('.theme-light');
        const autoIcon = button.querySelector('.theme-auto');
        const darkIcon = button.querySelector('.theme-dark');

        function updateIcon() {
            // Hide all icons in this selector
            lightIcon.style.display = 'none';
            autoIcon.style.display = 'none';
            darkIcon.style.display = 'none';
            
            // Show current theme icon
            switch(window.globalThemeState.currentTheme) {
                case 'light':
                    lightIcon.style.display = 'block';
                    break;
                case 'auto':
                    autoIcon.style.display = 'block';
                    break;
                case 'dark':
                    darkIcon.style.display = 'block';
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

        // Global function for syncing all selectors
        window.updateAllThemeSelectors = window.updateAllThemeSelectors || function() {
            document.querySelectorAll('.theme-selector').forEach(selector => {
                const light = selector.querySelector('.theme-light');
                const auto = selector.querySelector('.theme-auto');
                const dark = selector.querySelector('.theme-dark');
                
                light.style.display = 'none';
                auto.style.display = 'none';
                dark.style.display = 'none';
                
                switch(window.globalThemeState.currentTheme) {
                    case 'light':
                        light.style.display = 'block';
                        break;
                    case 'auto':
                        auto.style.display = 'block';
                        break;
                    case 'dark':
                        dark.style.display = 'block';
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
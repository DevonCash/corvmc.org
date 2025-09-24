<div class="flex items-center">
    <button 
        type="button"
        class="fi-icon-btn relative flex items-center justify-center rounded-lg outline-none transition duration-75 fi-color-gray fi-icon-btn-color-gray fi-size-md fi-icon-btn-size-md h-9 w-9 text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400"
        x-data="{ 
            theme: localStorage.getItem('theme') || 'auto',
            isApplying: false,
            applyTheme() {
                if (this.isApplying) return;
                this.isApplying = true;
                
                let isDark;
                if (this.theme === 'auto') {
                    isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                } else {
                    isDark = this.theme === 'dark';
                }
                
                const actualTheme = isDark ? 'corvmc-dark' : 'corvmc';
                document.documentElement.setAttribute('data-theme', actualTheme);
                document.documentElement.style.setProperty('color-scheme', isDark ? 'dark' : 'light');
                document.documentElement.classList.toggle('dark', isDark);
                
                this.isApplying = false;
            },
            cycleTheme() {
                const themes = ['light', 'auto', 'dark'];
                const currentIndex = themes.indexOf(this.theme);
                this.theme = themes[(currentIndex + 1) % themes.length];
                localStorage.setItem('theme', this.theme);
                this.applyTheme();
            }
        }"
        x-init="applyTheme()"
        @click="cycleTheme()"
    >
            <x-tabler-brightness-2 x-show="theme === 'light'" class="size-6" />
            <x-tabler-brightness-auto x-show="theme === 'auto'" class="size-6" />
            <x-tabler-brightness-half x-show="theme === 'dark'" class="size-6" />
    </button>
</div>
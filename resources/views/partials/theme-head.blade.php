{{-- Apply the saved dashboard theme before the page paints. Runs after @fluxAppearance so it wins. --}}
<script>
    window.hbThemes = @js(collect(\App\Support\Themes::all())->map(fn ($theme) => $theme['dark']));
    window.hbApplyTheme = (theme, save = false) => {
        if (! (theme in window.hbThemes)) theme = @js(\App\Support\Themes::DEFAULT);
        const root = document.documentElement;
        root.dataset.theme = theme;
        root.classList.toggle('dark', window.hbThemes[theme]);
        root.style.colorScheme = window.hbThemes[theme] ? 'dark' : 'light';
        document.querySelector('meta[name=theme-color]')?.setAttribute('content', getComputedStyle(root).getPropertyValue('--hb-bg').trim() || '#0b0810');
        if (save) {
            localStorage.setItem('hb-theme', theme);
            localStorage.setItem('flux.appearance', window.hbThemes[theme] ? 'dark' : 'light');
            window.dispatchEvent(new CustomEvent('hb-theme', { detail: theme }));
        }
    };
    window.hbApplyTheme(localStorage.getItem('hb-theme'));
    document.addEventListener('livewire:navigated', () => window.hbApplyTheme(localStorage.getItem('hb-theme')));
</script>

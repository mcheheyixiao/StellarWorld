// Theme manager with class-based dark mode + system preference fallback.
class ThemeManager {
    constructor() {
        this.themeToggle = null;
        this.storageKey = 'theme';
        this.currentTheme = 'dark';
        this.transitionTimer = null;
        this.init();
    }

    getStoredTheme() {
        try {
            const stored = localStorage.getItem(this.storageKey);
            return stored === 'light' || stored === 'dark' ? stored : null;
        } catch (e) {
            return null;
        }
    }

    getSystemTheme() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    resolveInitialTheme() {
        return this.getStoredTheme() || this.getSystemTheme();
    }

    init() {
        this.themeToggle = document.getElementById('theme-toggle');
        this.setTheme(this.resolveInitialTheme(), { persist: false, emit: false });
        if (this.themeToggle) {
            this.themeToggle.addEventListener('click', (event) => {
                event.preventDefault();
                this.toggleTheme();
            });
        }
        this.watchSystemTheme();
        this.updateToggleButton();
    }

    setTheme(theme, options = {}) {
        const persist = options.persist !== false;
        const emit = options.emit !== false;
        this.currentTheme = theme === 'light' ? 'light' : 'dark';
        const isDark = this.currentTheme === 'dark';
        const root = document.documentElement;

        root.classList.toggle('dark', isDark);
        root.classList.toggle('light', !isDark);
        root.setAttribute('data-theme', this.currentTheme);
        root.style.colorScheme = isDark ? 'dark' : 'light';

        if (persist) {
            try {
                localStorage.setItem(this.storageKey, this.currentTheme);
            } catch (e) {
                // ignore storage failures
            }
        }

        this.updateToggleButton();
        if (emit) {
            document.dispatchEvent(new CustomEvent('themeChange', { detail: { theme: this.currentTheme } }));
        }
    }

    toggleTheme() {
        const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        this.addTransitionEffect();
        this.setTheme(newTheme, { persist: true, emit: true });
    }

    addTransitionEffect() {
        const root = document.documentElement;
        root.classList.add('theme-transitioning');
        if (this.transitionTimer) {
            clearTimeout(this.transitionTimer);
        }
        this.transitionTimer = setTimeout(() => {
            root.classList.remove('theme-transitioning');
            this.transitionTimer = null;
        }, 450);
    }

    updateToggleButton() {
        if (!this.themeToggle) return;
        const isDark = this.currentTheme === 'dark';
        this.themeToggle.classList.toggle('is-dark', isDark);
        this.themeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
        this.themeToggle.setAttribute('title', isDark ? '当前深色模式' : '当前浅色模式');
    }

    watchSystemTheme() {
        if (!window.matchMedia) return;
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        mediaQuery.addEventListener('change', (e) => {
            if (!this.getStoredTheme()) {
                this.setTheme(e.matches ? 'dark' : 'light', { persist: false, emit: true });
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.themeManager = new ThemeManager();
});


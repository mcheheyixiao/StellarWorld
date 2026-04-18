// Ported theme manager
class ThemeManager {
    constructor() {
        this.themeToggle = null;
        this.currentTheme = this.getStoredTheme()
            || document.documentElement.getAttribute('data-theme')
            || 'dark';
        this.init();
    }

    getStoredTheme() {
        try {
            return localStorage.getItem('theme');
        } catch (e) {
            return null;
        }
    }

    init() {
        this.themeToggle = document.getElementById('theme-toggle');
        this.setTheme(this.currentTheme);
        if (this.themeToggle) {
            this.themeToggle.addEventListener('click', (event) => {
                event.preventDefault();
                this.toggleTheme();
            });
        }
        this.watchSystemTheme();
    }

    setTheme(theme) {
        this.currentTheme = theme === 'light' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', this.currentTheme);
        try {
            localStorage.setItem('theme', this.currentTheme);
        } catch (e) {
            // ignore storage failures
        }
        this.updateToggleButton();
        document.dispatchEvent(new CustomEvent('themeChange', { detail: { theme: this.currentTheme } }));
    }

    toggleTheme() {
        const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        this.setTheme(newTheme);
        this.addTransitionEffect();
    }

    addTransitionEffect() {
        document.body.style.transition = 'none';
        document.body.style.opacity = '0.8';
        requestAnimationFrame(() => {
            document.body.style.transition = 'opacity 0.3s var(--ease-smooth)';
            document.body.style.opacity = '1';
        });
        setTimeout(() => {
            document.body.style.transition = '';
        }, 300);
    }

    updateToggleButton() {
        if (!this.themeToggle) return;
        const isDark = this.currentTheme === 'dark';
        this.themeToggle.classList.toggle('is-dark', isDark);
        this.themeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
    }

    watchSystemTheme() {
        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            if (!this.getStoredTheme()) {
                this.setTheme(mediaQuery.matches ? 'dark' : 'light');
            }
            mediaQuery.addEventListener('change', (e) => {
                if (!this.getStoredTheme()) {
                    this.setTheme(e.matches ? 'dark' : 'light');
                }
            });
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.themeManager = new ThemeManager();
});


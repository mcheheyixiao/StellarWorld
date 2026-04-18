class FontManager {
    constructor() {
        this.fontConfig = null;
        this.init();
    }

    async init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.loadFontConfig());
        } else {
            await this.loadFontConfig();
        }
    }

    async loadFontConfig() {
        try {
            if (window.websiteConfig && window.websiteConfig.fonts) {
                this.fontConfig = window.websiteConfig.fonts;
                this.applyFonts();
            } else {
                this.useDefaultFonts();
            }
        } catch (error) {
            console.warn('无法加载字体配置，使用默认字体:', error);
            this.useDefaultFonts();
        }
    }

    useDefaultFonts() {
        this.fontConfig = {
            main: {
                family: '像素体',
                file: '/fonts/像素体.ttf',
                fallback: "'Quicksand', 'Noto Sans SC', sans-serif",
                weight: 'normal'
            },
            title: {
                family: '像素体',
                file: '/fonts/像素体.ttf',
                fallback: "'Quicksand', 'Noto Sans SC', sans-serif",
                weight: 'bold'
            }
        };
        this.applyFonts();
    }

    applyFonts() {
        if (!this.fontConfig) return;
        this.createFontCSS();
        this.setCSSVariables();
        this.preloadFonts();
    }

    createFontCSS() {
        const style = document.createElement('style');
        style.id = 'dynamic-fonts';

        let css = '';
        if (this.fontConfig.main) {
            css += `
@font-face {
    font-family: '${this.fontConfig.main.family}';
    src: url('${this.fontConfig.main.file}') format('truetype');
    font-weight: ${this.fontConfig.main.weight};
    font-style: normal;
    font-display: swap;
}
`;
        }

        if (this.fontConfig.title && this.fontConfig.title.family !== this.fontConfig.main.family) {
            css += `
@font-face {
    font-family: '${this.fontConfig.title.family}';
    src: url('${this.fontConfig.title.file}') format('truetype');
    font-weight: ${this.fontConfig.title.weight};
    font-style: normal;
    font-display: swap;
}
`;
        }

        style.textContent = css;
        const existingStyle = document.getElementById('dynamic-fonts');
        if (existingStyle) {
            existingStyle.remove();
        }
        document.head.appendChild(style);
    }

    setCSSVariables() {
        const root = document.documentElement;
        if (this.fontConfig.main) {
            const mainFontStack = `'${this.fontConfig.main.family}', ${this.fontConfig.main.fallback}`;
            root.style.setProperty('--font-main', mainFontStack);
        }
        if (this.fontConfig.title) {
            const titleFontStack = `'${this.fontConfig.title.family}', ${this.fontConfig.title.fallback}`;
            root.style.setProperty('--font-title', titleFontStack);
        }
    }

    preloadFonts() {
        const fontsToPreload = new Set();
        if (this.fontConfig.main && this.fontConfig.main.file) {
            fontsToPreload.add(this.fontConfig.main.file);
        }
        if (this.fontConfig.title && this.fontConfig.title.file && this.fontConfig.title.file !== this.fontConfig.main.file) {
            fontsToPreload.add(this.fontConfig.title.file);
        }
        fontsToPreload.forEach(fontFile => {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.href = fontFile;
            link.as = 'font';
            link.type = 'font/ttf';
            link.crossOrigin = 'anonymous';
            document.head.appendChild(link);
        });
    }
}

window.fontManager = new FontManager();


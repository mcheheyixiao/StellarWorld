class LoadingManager {
    constructor() {
        this.loadingSpinner = document.getElementById('loading-spinner');
        this.progressFill = document.querySelector('.progress-fill');
        this.isLoading = false;
        this.minLoadingTime = 800;
        this.startTime = 0;
        this.init();
    }

    init() {
        if (!this.loadingSpinner) return;
        this.setupPageLoadListener();
        this.setupNavigationListener();
        this.setupVisibilityListener();
    }

    setupPageLoadListener() {
        this.showLoading();
        this.simulateProgress();

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.hideLoading();
            });
        } else {
            setTimeout(() => {
                this.hideLoading();
            }, this.minLoadingTime);
        }

        setTimeout(() => {
            if (this.isLoading) {
                this.hideLoading();
            }
        }, 1500);
    }

    setupNavigationListener() {
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a');
            if (link && this.isInternalLink(link.href)) {
                e.preventDefault();
                this.handleNavigation(link.href);
            }
        });

        window.addEventListener('popstate', () => {
            this.showLoading();
            this.simulateProgress();
            setTimeout(() => this.hideLoading(), this.minLoadingTime);
        });
    }

    setupVisibilityListener() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseAnimations();
            } else {
                this.resumeAnimations();
            }
        });
    }

    isInternalLink(href) {
        try {
            const url = new URL(href, window.location.origin);
            return url.origin === window.location.origin &&
                !href.startsWith('mailto:') &&
                !href.startsWith('tel:') &&
                !href.includes('#');
        } catch {
            return false;
        }
    }

    handleNavigation(url) {
        this.showLoading();
        this.simulateProgress();
        setTimeout(() => {
            window.location.href = url;
        }, this.minLoadingTime);
    }

    showLoading() {
        if (this.isLoading) return;
        this.isLoading = true;
        this.startTime = Date.now();
        this.loadingSpinner.style.display = 'flex';
        requestAnimationFrame(() => {
            this.loadingSpinner.classList.add('show');
        });
        document.body.style.overflow = 'hidden';
        document.dispatchEvent(new CustomEvent('loadingStart'));
    }

    hideLoading() {
        if (!this.isLoading) return;
        const elapsedTime = Date.now() - this.startTime;
        const remainingTime = Math.max(0, this.minLoadingTime - elapsedTime);

        setTimeout(() => {
            this.loadingSpinner.classList.remove('show');
            setTimeout(() => {
                this.loadingSpinner.style.display = 'none';
                this.isLoading = false;
                document.body.style.overflow = '';
                if (this.progressFill) {
                    this.progressFill.style.width = '0%';
                }
                document.dispatchEvent(new CustomEvent('loadingComplete'));
            }, 300);
        }, remainingTime);
    }

    simulateProgress() {
        if (!this.progressFill) return;
        let progress = 0;
        const interval = setInterval(() => {
            if (progress >= 90) {
                clearInterval(interval);
                return;
            }
            progress += Math.random() * 10;
            this.progressFill.style.width = Math.min(progress, 90) + '%';
        }, 200);

        window.addEventListener('load', () => {
            clearInterval(interval);
            this.progressFill.style.width = '100%';
        });
    }

    pauseAnimations() {
        const animations = this.loadingSpinner.getAnimations();
        animations.forEach(animation => animation.pause());
    }

    resumeAnimations() {
        const animations = this.loadingSpinner.getAnimations();
        animations.forEach(animation => animation.play());
    }
}

window.loadingManager = new LoadingManager();


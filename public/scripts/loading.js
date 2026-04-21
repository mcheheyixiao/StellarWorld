class LoadingManager {
    constructor() {
        this.loadingSpinner = document.getElementById('loading-spinner');
        this.progressFill = document.querySelector('.progress-fill');
        this.isLoading = false;
        this.startTime = 0;
        this.slowLoadThreshold = 450;
        this.maxInitialLoadTime = 3000;
        this.progressInterval = null;
        this.pageLoadTimer = null;
        this.fallbackHideTimer = null;
        this.navigationTimer = null;
        this.init();
    }

    init() {
        if (!this.loadingSpinner) return;
        this.setupPageLoadListener();
        this.setupNavigationListener();
        this.setupVisibilityListener();
    }

    setupPageLoadListener() {
        const completeInitialLoad = () => {
            this.clearPageLoadTimers();
            this.clearNavigationTimer();
            this.finishProgress();
            this.hideLoading();
        };

        this.pageLoadTimer = window.setTimeout(() => {
            if (document.readyState !== 'complete') {
                this.showLoading();
                this.simulateProgress();
            }
        }, this.slowLoadThreshold);

        this.fallbackHideTimer = window.setTimeout(() => {
            completeInitialLoad();
        }, this.maxInitialLoadTime);

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', completeInitialLoad, { once: true });
            window.addEventListener('load', completeInitialLoad, { once: true });
        } else {
            completeInitialLoad();
        }
    }

    setupNavigationListener() {
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a');
            if (link && this.isInternalLink(link)) {
                this.scheduleSlowLoading();
            }
        }, true);

        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (!(form instanceof HTMLFormElement)) return;
            const action = form.getAttribute('action') || window.location.href;
            if (!this.isInternalUrl(action)) return;
            this.scheduleSlowLoading();
        }, true);

        window.addEventListener('beforeunload', () => {
            this.scheduleSlowLoading();
        });

        window.addEventListener('popstate', () => {
            this.scheduleSlowLoading(180);
            setTimeout(() => {
                this.finishProgress();
                this.hideLoading();
            }, 800);
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

    isInternalLink(link) {
        if (!(link instanceof HTMLAnchorElement)) {
            return false;
        }
        if (link.target === '_blank' || link.hasAttribute('download')) {
            return false;
        }
        const href = link.getAttribute('href') || '';
        if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) {
            return false;
        }
        if (link.pathname === window.location.pathname && link.search === window.location.search && link.hash) {
            return false;
        }
        return this.isInternalUrl(link.href);
    }

    isInternalUrl(href) {
        try {
            const url = new URL(href, window.location.origin);
            return url.origin === window.location.origin;
        } catch {
            return false;
        }
    }

    scheduleSlowLoading(delay = this.slowLoadThreshold) {
        this.clearNavigationTimer();
        this.navigationTimer = window.setTimeout(() => {
            this.navigationTimer = null;
            this.showLoading();
            this.simulateProgress();
        }, delay);
    }

    clearNavigationTimer() {
        if (this.navigationTimer !== null) {
            clearTimeout(this.navigationTimer);
            this.navigationTimer = null;
        }
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
    }

    simulateProgress() {
        if (!this.progressFill) return;
        this.stopProgress();
        let progress = 12;
        this.progressFill.style.width = progress + '%';
        this.progressInterval = setInterval(() => {
            if (progress >= 88) {
                return;
            }
            progress += Math.random() * 8;
            this.progressFill.style.width = Math.min(progress, 88) + '%';
        }, 200);
    }

    finishProgress() {
        if (!this.progressFill) return;
        this.stopProgress();
        this.progressFill.style.width = '100%';
    }

    stopProgress() {
        if (this.progressInterval !== null) {
            clearInterval(this.progressInterval);
            this.progressInterval = null;
        }
    }

    clearPageLoadTimers() {
        if (this.pageLoadTimer !== null) {
            clearTimeout(this.pageLoadTimer);
            this.pageLoadTimer = null;
        }
        if (this.fallbackHideTimer !== null) {
            clearTimeout(this.fallbackHideTimer);
            this.fallbackHideTimer = null;
        }
        this.clearNavigationTimer();
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


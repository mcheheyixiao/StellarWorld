class NavigationManager {
    constructor() {
        this.navbar = document.getElementById('navbar');
        this.lastScrollTop = 0;
        this.hideThreshold = 100;
        this.showThreshold = 50;
        this.isScrolling = false;

        this.init();
    }

    init() {
        if (!this.navbar) return;
        window.addEventListener('scroll', () => this.handleScroll());
        this.initMobileMenu();
        this.checkScrollPosition();
    }

    handleScroll() {
        if (this.isScrolling) return;
        this.isScrolling = true;
        requestAnimationFrame(() => {
            this.checkScrollPosition();
            this.isScrolling = false;
        });
    }

    checkScrollPosition() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        if (scrollTop > this.lastScrollTop && scrollTop > this.hideThreshold) {
            this.hideNavbar();
        } else if (scrollTop < this.lastScrollTop && scrollTop < this.showThreshold) {
            this.showNavbar();
        }
        this.lastScrollTop = scrollTop;
    }

    hideNavbar() {
        if (this.navbar && !this.navbar.classList.contains('hidden')) {
            this.navbar.classList.add('hidden');
            document.dispatchEvent(new CustomEvent('navbarHide'));
        }
    }

    showNavbar() {
        if (this.navbar && this.navbar.classList.contains('hidden')) {
            this.navbar.classList.remove('hidden');
            document.dispatchEvent(new CustomEvent('navbarShow'));
        }
    }

    initMobileMenu() {
        const menuToggle = document.getElementById('mobile-menu-toggle');
        const navMenu = document.querySelector('.navbar-menu');
        if (!menuToggle || !navMenu) return;

        menuToggle.addEventListener('click', () => {
            navMenu.classList.toggle('active');
            menuToggle.classList.toggle('active');
            const icon = menuToggle.querySelector('i');
            if (icon) {
                icon.className = navMenu.classList.contains('active') ? 'mdi mdi-close' : 'mdi mdi-menu';
            }
        });

        const navLinks = navMenu.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                navMenu.classList.remove('active');
                menuToggle.classList.remove('active');
                const icon = menuToggle.querySelector('i');
                if (icon) icon.className = 'mdi mdi-menu';
            });
        });

        document.addEventListener('click', (e) => {
            if (!menuToggle.contains(e.target) && !navMenu.contains(e.target)) {
                navMenu.classList.remove('active');
                menuToggle.classList.remove('active');
                const icon = menuToggle.querySelector('i');
                if (icon) icon.className = 'mdi mdi-menu';
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.navigationManager = new NavigationManager();
});


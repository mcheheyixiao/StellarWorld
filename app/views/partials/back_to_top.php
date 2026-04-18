<button id="backToTop" class="back-to-top" aria-label="回到顶部">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
</button>

<script>
    (function () {
        var btn = document.getElementById('backToTop');
        if (!btn) return;
        window.addEventListener('scroll', function () {
            if (window.scrollY > 200) {
                btn.classList.add('visible');
            } else {
                btn.classList.remove('visible');
            }
        });
        btn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    })();
</script>

<style>
.back-to-top {
    position: fixed;
    right: 1.5rem;
    bottom: 5.5rem;
    width: 44px;
    height: 44px;
    border-radius: 999px;
    border: none;
    background: rgba(14, 165, 233, 0.3);
    color: #e0f2fe;
    border: 1px solid rgba(34, 211, 238, 0.4);
    backdrop-filter: blur(12px);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 16px 36px -20px rgba(0, 0, 0, 0.7), 0 0 18px -10px rgba(34, 211, 238, 0.5);
    cursor: pointer;
    opacity: 0;
    visibility: hidden;
    transform: translateY(16px);
    transition: all 0.3s var(--ease-smooth);
    z-index: 1000;
}

.back-to-top.visible {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}
.back-to-top:hover {
    background: rgba(14, 165, 233, 0.45);
}

[data-theme="light"] .back-to-top {
    background: rgba(255, 255, 255, 0.88);
    color: #0e7490;
    border-color: rgba(148, 163, 184, 0.4);
    box-shadow: 0 14px 30px -18px rgba(15, 23, 42, 0.32);
}

[data-theme="light"] .back-to-top:hover {
    background: rgba(224, 242, 254, 0.95);
}
</style>


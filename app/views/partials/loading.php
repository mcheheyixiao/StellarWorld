<div id="loading-spinner" class="loading-overlay">
    <div class="loading-content">
        <div class="spinner-ring"></div>
        <div class="loading-text">繁星World 正在载入...</div>
        <div class="loading-progress">
            <div class="progress-fill"></div>
        </div>
    </div>
</div>

<style>
.loading-overlay {
    position: fixed;
    inset: 0;
    background: rgba(2, 6, 23, 0.72);
    backdrop-filter: blur(16px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    opacity: 0;
    transition: opacity 0.3s var(--ease-smooth);
}

.loading-overlay.show {
    opacity: 1;
}

.loading-content {
    background: rgba(2, 6, 23, 0.78);
    border-radius: 1rem;
    padding: 2rem 2.5rem;
    border: 1px solid rgba(148, 163, 184, 0.22);
    box-shadow: 0 22px 60px -34px rgba(0, 0, 0, 0.85), 0 0 22px -10px rgba(34, 211, 238, 0.35);
    text-align: center;
}

.spinner-ring {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    border: 4px solid rgba(148, 163, 184, 0.25);
    border-top-color: #22d3ee;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem;
}

.loading-text {
    color: #e2e8f0;
    font-weight: 600;
    margin-bottom: 0.75rem;
}

.loading-progress {
    width: 220px;
    height: 4px;
    border-radius: 999px;
    background: rgba(148, 163, 184, 0.25);
    overflow: hidden;
}

[data-theme="light"] .loading-overlay {
    background: rgba(226, 232, 240, 0.72);
}

[data-theme="light"] .loading-content {
    background: rgba(255, 255, 255, 0.94);
    border-color: rgba(148, 163, 184, 0.36);
    box-shadow: 0 20px 48px -28px rgba(15, 23, 42, 0.35), 0 0 18px -10px rgba(14, 165, 233, 0.28);
}

[data-theme="light"] .spinner-ring {
    border: 4px solid rgba(100, 116, 139, 0.26);
    border-top-color: #0891b2;
}

[data-theme="light"] .loading-text {
    color: #0f172a;
}

[data-theme="light"] .loading-progress {
    background: rgba(148, 163, 184, 0.32);
}

.progress-fill {
    width: 0;
    height: 100%;
    background: linear-gradient(90deg, #06b6d4, #38bdf8);
    transition: width 0.2s var(--ease-smooth);
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>


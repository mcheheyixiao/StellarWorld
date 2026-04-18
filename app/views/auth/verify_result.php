<div class="page-container">
    <div class="mc-glass-card fade-in w-full p-6 md:p-8 text-center">
        <h1 class="text-fusion-pixel mb-4 text-2xl text-white">邮箱验证结果</h1>
        <p style="margin-bottom:1.5rem;color:#94a3b8;"><?= htmlspecialchars($message ?? '', ENT_QUOTES, 'UTF-8') ?></p>
        <a href="/auth/login" class="btn" style="border-radius:.75rem;background:rgba(14,165,233,.32);border:1px solid rgba(34,211,238,.4);color:#e0f2fe;">
            前往登录
        </a>
    </div>
</div>


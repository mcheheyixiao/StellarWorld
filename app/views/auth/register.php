<div class="page-container">
    <?php $oauthPendingEmail = trim((string)($oauthPendingEmail ?? ($_SESSION['oauth_pending_user']['email'] ?? ''))); ?>
    <div class="mc-glass-card fade-in w-full p-6 md:p-8">
        <h1 class="text-fusion-pixel mb-4 text-2xl text-white">玩家注册</h1>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        <form id="registerForm" method="post" action="/auth/register">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-4">
                <label class="mb-1 block text-sm text-slate-300" for="username">用户名</label>
                <input id="username" name="username" type="text" required class="custom-input custom-input--no-icon w-full" autocomplete="username">
            </div>
            <div class="mb-4">
                <label class="mb-1 block text-sm text-slate-300" for="mc_username">绑定游戏名（可选）</label>
                <input id="mc_username" name="mc_username" type="text" class="custom-input custom-input--no-icon w-full"
                       placeholder="仅填写游戏名，网站将自动生成 UUID" autocomplete="off">
            </div>
            <div class="mb-4">
                <label class="mb-1 block text-sm text-slate-300" for="email">邮箱</label>
                <input id="email" name="email" type="email" required class="custom-input custom-input--no-icon w-full"
                       value="<?= htmlspecialchars($oauthPendingEmail, ENT_QUOTES, 'UTF-8') ?>"
                       <?= $oauthPendingEmail !== '' ? 'readonly' : '' ?>
                       autocomplete="email">
                <p class="mt-2 text-xs text-slate-400">
                    当前仅支持 QQ / 网易 / Gmail / Outlook 等主流邮箱，暂不支持临时邮箱。
                </p>
            </div>
            <div class="mb-4">
                <label class="mb-1 block text-sm text-slate-300" for="password">密码</label>
                <input id="password" name="password" type="password" required class="custom-input custom-input--no-icon w-full" autocomplete="new-password">
            </div>
            <div style="margin-top:0.5rem;margin-bottom:0.5rem;display:flex;justify-content:center;">
                <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars((string)TURNSTILE_SITE_KEY, ENT_QUOTES, 'UTF-8') ?>"></div>
            </div>
            <button type="submit" class="auth-action-btn auth-action-btn--primary mt-2 w-full rounded-xl border border-cyan-300/35 bg-cyan-500/20 py-3 text-base font-semibold text-cyan-200 transition-all sm:text-lg">
                注册
            </button>
            <a href="/auth/mua" class="auth-action-btn auth-action-btn--secondary mt-3 block w-full rounded-xl border border-white/10 bg-slate-900/70 py-3 text-center text-base font-semibold text-slate-200 transition-all sm:text-lg">
                🎮 使用 MUA 账号快速注册
            </a>
        </form>
        <p style="margin-top:1rem;font-size:0.9rem;color:#94a3b8;">
            已有账号？<a href="/auth/login">前往登录</a>
        </p>
        <div id="registerMessage" style="margin-top:0.75rem;font-size:0.9rem;color:#cbd5e1;"></div>
    </div>
</div>

<script>
const AUTH_COOLDOWN_SECONDS = <?= (int)(defined('AUTH_ACTION_COOLDOWN') ? AUTH_ACTION_COOLDOWN : 60) ?>;

function safeTurnstileReset() {
    try {
        if (window.turnstile && typeof window.turnstile.reset === 'function') {
            window.turnstile.reset();
        }
    } catch (e) {
        // ignore
    }
}

function startButtonCooldown(btn, originalText, seconds) {
    let remain = seconds;
    btn.disabled = true;
    btn.textContent = `请稍候 (${remain}s)`;

    const timer = setInterval(() => {
        remain -= 1;
        if (remain <= 0) {
            clearInterval(timer);
            btn.disabled = false;
            btn.textContent = originalText;
            safeTurnstileReset();
            return;
        }
        btn.textContent = `请稍候 (${remain}s)`;
    }, 1000);
}

document.getElementById('registerForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = this;
    const msgBox = document.getElementById('registerMessage');
    const btn = form.querySelector('button[type="submit"]');
    const originalText = btn ? btn.textContent.trim() : '注册';
    if (btn) {
        btn.disabled = true;
        btn.textContent = '正在提交...';
    }
    msgBox.textContent = '正在提交注册...';
    try {
        const formData = new FormData(form);
        const resp = await fetch(form.action, {
            method: 'POST',
            body: formData
        });
        const data = await resp.json();
        msgBox.textContent = data.message || (data.success ? '注册成功' : '注册失败');
        if (resp.status === 429 || data.success) {
            form.reset();
            if (btn) {
                startButtonCooldown(btn, originalText, AUTH_COOLDOWN_SECONDS);
            }
            return;
        }
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
        safeTurnstileReset();
    } catch (err) {
        console.error(err);
        msgBox.textContent = '网络错误，请稍后重试';
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
        safeTurnstileReset();
    }
});
</script>


<div class="page-container">
    <div class="mc-glass-card fade-in w-full p-6 md:p-8">
        <h1 class="text-fusion-pixel mb-4 text-2xl text-white">找回密码</h1>
        <form id="forgotForm" method="post" action="/forgot-password" data-async-form="true">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <div style="margin-bottom:1rem;">
                <label for="email">邮箱</label>
                <input id="email" name="email" type="email" required
                       style="width:100%;margin-top:0.3rem;padding:0.6rem 0.8rem;border-radius:.7rem;border:1px solid rgba(148,163,184,.3);background:rgba(15,23,42,.72);color:#e2e8f0;">
                <small style="color:#94a3b8;font-size:0.8rem;">
                    我们会向该邮箱发送一封包含密码重置链接的邮件。
                </small>
            </div>
            <div style="margin-bottom:1rem;">
                <label for="forgotCaptchaAnswer">图形验证码</label>
                <div style="margin-top:0.45rem;display:flex;flex-wrap:wrap;align-items:center;gap:0.75rem;">
                    <img id="forgotCaptchaImage" src="/auth/captcha?purpose=forgot_password" alt="图形验证码" style="width:240px;height:64px;border-radius:0.85rem;border:1px solid rgba(103,232,249,.3);background:rgba(15,23,42,.9);object-fit:cover;">
                    <button type="button" id="forgotCaptchaRefresh" class="auth-action-btn auth-action-btn--secondary rounded-xl border border-white/10 bg-slate-900/70 px-4 py-2 text-sm font-semibold text-slate-200 transition-all">
                        刷新验证码
                    </button>
                </div>
                <input id="forgotCaptchaAnswer" name="captcha_answer" type="text" required class="custom-input custom-input--no-icon mt-3 w-full" placeholder="请输入图片中的计算结果" autocomplete="off" inputmode="numeric">
            </div>
            <button type="submit" class="btn" style="width:100%;margin-top:0.5rem;border-radius:.75rem;background:rgba(14,165,233,.32);border:1px solid rgba(34,211,238,.4);color:#e0f2fe;">
                发送重置链接
            </button>
        </form>
        <p style="margin-top:1rem;font-size:0.9rem;color:#94a3b8;">
            想起密码了？<a href="/auth/login">返回登录</a>
        </p>
        <div id="forgotMessage" style="margin-top:0.75rem;font-size:0.9rem;color:#cbd5e1;"></div>
    </div>
</div>

<script>
const AUTH_COOLDOWN_SECONDS = <?= (int)(defined('AUTH_ACTION_COOLDOWN') ? AUTH_ACTION_COOLDOWN : 60) ?>;

function refreshForgotCaptcha() {
    const image = document.getElementById('forgotCaptchaImage');
    const answer = document.getElementById('forgotCaptchaAnswer');
    if (image) {
        image.src = '/auth/captcha?purpose=forgot_password&t=' + Date.now();
    }
    if (answer) {
        answer.value = '';
    }
}

function startButtonCooldown(btn, originalText, seconds) {
    let remain = seconds;
    btn.disabled = true;
    btn.textContent = `重新发送 (${remain}s)`;

    const timer = setInterval(() => {
        remain -= 1;
        if (remain <= 0) {
            clearInterval(timer);
            btn.disabled = false;
            btn.textContent = originalText;
            return;
        }
        btn.textContent = `重新发送 (${remain}s)`;
    }, 1000);
}

function hideGlobalLoadingIfPresent() {
    if (window.loadingManager && typeof window.loadingManager.cancelPendingNavigationLoading === 'function') {
        window.loadingManager.cancelPendingNavigationLoading();
        return;
    }
    if (window.loadingManager && typeof window.loadingManager.hideLoading === 'function') {
        if (typeof window.loadingManager.clearNavigationTimer === 'function') {
            window.loadingManager.clearNavigationTimer();
        }
        window.loadingManager.hideLoading();
    }
}

async function readJsonSafe(resp) {
    try {
        return await resp.json();
    } catch (err) {
        return {
            success: false,
            message: '响应异常，请稍后重试'
        };
    }
}

document.getElementById('forgotCaptchaRefresh').addEventListener('click', refreshForgotCaptcha);

document.getElementById('forgotForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = this;
    const msgBox = document.getElementById('forgotMessage');
    const btn = form.querySelector('button[type="submit"]');
    const originalText = btn ? btn.textContent.trim() : '发送重置链接';
    hideGlobalLoadingIfPresent();

    if (btn) {
        btn.disabled = true;
        btn.textContent = '正在发送...';
    }
    msgBox.textContent = '正在发送...';
    try {
        const formData = new FormData(form);
        const resp = await fetch(form.action, {
            method: 'POST',
            body: formData
        });
        const data = await readJsonSafe(resp);
        msgBox.textContent = data.message || (data.success ? '发送成功' : '发送失败');

        if (resp.status === 429 || data.success) {
            form.reset();
            refreshForgotCaptcha();
            if (btn) {
                startButtonCooldown(btn, originalText, AUTH_COOLDOWN_SECONDS);
            }
            return;
        }

        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
        refreshForgotCaptcha();
    } catch (err) {
        console.error(err);
        msgBox.textContent = '网络错误，请稍后重试';
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
        refreshForgotCaptcha();
    } finally {
        hideGlobalLoadingIfPresent();
    }
});
</script>

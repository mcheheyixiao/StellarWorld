<div class="page-container auth-page-shell">
    <div class="auth-card fade-in">
        <div class="auth-card__brand">
            <img class="auth-card__logo-image" src="/images/email.png" alt="繁星World 图标" width="176" height="88">
            <h1 class="auth-card__title auth-card__title--center text-fusion-pixel">找回密码</h1>
            <p class="auth-card__subtitle">输入绑定邮箱后，我们会发送密码重置链接。</p>
        </div>

        <form id="forgotForm" method="post" action="/forgot-password" data-async-form="true" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <div class="auth-field">
                <label class="auth-label" for="email">邮箱</label>
                <input id="email" name="email" type="email" required class="auth-input" autocomplete="email">
                <p class="auth-help-text">请输入注册时绑定的邮箱地址。</p>
            </div>

            <div class="auth-field">
                <label class="auth-label" for="forgotCaptchaAnswer">图形验证码</label>
                <div class="auth-captcha-row">
                    <img id="forgotCaptchaImage" class="auth-captcha-image" src="/auth/captcha?purpose=forgot_password" alt="图形验证码">
                    <button type="button" id="forgotCaptchaRefresh" class="auth-button auth-button--secondary sm:w-auto">
                        刷新验证码
                    </button>
                </div>
                <input id="forgotCaptchaAnswer" name="captcha_answer" type="text" required class="auth-input" placeholder="请输入图片中的计算结果" autocomplete="off" inputmode="numeric">
            </div>

            <button type="submit" class="auth-button auth-button--primary">发送重置链接</button>
        </form>

        <div class="auth-divider">记起来了</div>
        <p class="auth-link-row">想起密码了？<a href="/auth/login">返回登录</a></p>
        <div id="forgotMessage" class="auth-message"></div>
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
    btn.textContent = `Retry (${remain}s)`;

    const timer = setInterval(() => {
        remain -= 1;
        if (remain <= 0) {
            clearInterval(timer);
            btn.disabled = false;
            btn.textContent = originalText;
            return;
        }
        btn.textContent = `Retry (${remain}s)`;
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
            message: 'Response parse failed, please retry.'
        };
    }
}

document.getElementById('forgotCaptchaRefresh').addEventListener('click', refreshForgotCaptcha);

document.getElementById('forgotForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = this;
    const msgBox = document.getElementById('forgotMessage');
    const btn = form.querySelector('button[type="submit"]');
    const originalText = btn ? btn.textContent.trim() : 'Send reset link';
    hideGlobalLoadingIfPresent();

    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Sending...';
    }
    msgBox.textContent = 'Sending...';
    try {
        const formData = new FormData(form);
        const resp = await fetch(form.action, {
            method: 'POST',
            body: formData
        });
        const data = await readJsonSafe(resp);
        msgBox.textContent = data.message || (data.success ? 'Request sent.' : 'Request failed.');

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
        msgBox.textContent = 'Network error, please retry.';
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

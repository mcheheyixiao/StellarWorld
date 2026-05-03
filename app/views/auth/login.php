<div class="page-container auth-page-shell">
    <div class="auth-card fade-in">
        <div class="auth-card__brand">
            <img class="auth-card__logo-image" src="/images/email.png" alt="繁星World 图标" width="176" height="88">
            <h1 class="auth-card__title auth-card__title--center text-fusion-pixel">繁星World</h1>
            <p class="auth-card__subtitle">登录你的玩家账户</p>
        </div>

        <form id="loginForm" method="post" action="/auth/login" data-async-form="true" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <div class="auth-field">
                <label class="auth-label" for="username">用户名</label>
                <div class="auth-input-group">
                    <svg class="auth-input-icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5zm0 2c-3.866 0-7 2.239-7 5v1h14v-1c0-2.761-3.134-5-7-5z"/>
                    </svg>
                    <input id="username" name="username" type="text" required class="auth-input" placeholder="请输入用户名" autocomplete="username">
                </div>
            </div>

            <div class="auth-field">
                <label class="auth-label" for="password">密码</label>
                <div class="auth-input-group">
                    <svg class="auth-input-icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M17 8h-1V6c0-2.757-2.243-5-5-5S6 3.243 6 6v2H5c-1.103 0-2 .897-2 2v12c0 1.103.897 2 2 2h12c1.103 0 2-.897 2-2V10c0-1.103-.897-2-2-2zm-7-2c0-1.654 1.346-3 3-3s3 1.346 3 3v2H10V6zm7 16H5V10h12v12z"/>
                    </svg>
                    <input id="password" name="password" type="password" required class="auth-input" placeholder="请输入密码" autocomplete="current-password">
                </div>
            </div>

            <div class="auth-field">
                <label class="auth-label" for="loginCaptchaAnswer">图形验证码</label>
                <div class="auth-captcha-row">
                    <img id="loginCaptchaImage" class="auth-captcha-image" src="/auth/captcha?purpose=login" alt="图形验证码">
                    <button type="button" id="loginCaptchaRefresh" class="auth-button auth-button--secondary sm:w-auto">
                        刷新验证码
                    </button>
                </div>
                <input id="loginCaptchaAnswer" name="captcha_answer" type="text" required class="auth-input" placeholder="请输入图片中的计算结果" autocomplete="off" inputmode="numeric">
            </div>

            <div class="auth-remember-row">
                <label class="auth-remember-label" for="remember">
                    <input type="checkbox" id="remember" name="remember" value="1" class="auth-remember-checkbox">
                    <span class="auth-remember-text">保持登录</span>
                </label>
            </div>

            <button type="submit" class="auth-button auth-button--primary">登录</button>
            <a href="/auth/mua" class="auth-button auth-button--mua">使用 MUA 账号登录</a>
            <?php if (defined('MICROSOFT_MINECRAFT_LOGIN_ENABLED') && MICROSOFT_MINECRAFT_LOGIN_ENABLED): ?>
                <a href="/auth/microsoft" class="auth-button auth-button--microsoft">使用 Microsoft / Xbox 正版账号登录</a>
            <?php endif; ?>
        </form>

        <div class="auth-divider">更多选项</div>
        <p class="auth-link-row">
            还没有账号？<a href="/auth/register">前往注册</a>
            <span aria-hidden="true">·</span>
            <a href="/forgot-password">忘记密码？</a>
        </p>
        <div id="loginMessage" class="auth-message"></div>
    </div>
</div>

<script>
function refreshLoginCaptcha() {
    const image = document.getElementById('loginCaptchaImage');
    const answer = document.getElementById('loginCaptchaAnswer');
    if (!image) return;
    image.src = '/auth/captcha?purpose=login&t=' + Date.now();
    if (answer) {
        answer.value = '';
    }
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

document.getElementById('loginCaptchaRefresh').addEventListener('click', refreshLoginCaptcha);

document.getElementById('loginForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = this;
    const msgBox = document.getElementById('loginMessage');
    const btn = form.querySelector('button[type="submit"]');
    const originalText = btn ? btn.textContent.trim() : '登录';
    hideGlobalLoadingIfPresent();
    if (btn) {
        btn.disabled = true;
        btn.textContent = '正在登录...';
    }
    msgBox.textContent = '正在登录...';
    try {
        const formData = new FormData(form);
        const resp = await fetch(form.action, {
            method: 'POST',
            body: formData
        });
        const data = await readJsonSafe(resp);
        msgBox.textContent = data.message || (data.success ? '登录成功' : '登录失败');
        if (data.success) {
            setTimeout(function () { window.location.href = '/'; }, 800);
            return;
        }
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
        refreshLoginCaptcha();
    } catch (err) {
        console.error(err);
        msgBox.textContent = '网络错误，请稍后重试';
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
        refreshLoginCaptcha();
    } finally {
        hideGlobalLoadingIfPresent();
    }
});
</script>

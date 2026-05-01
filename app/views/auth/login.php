<div class="page-container">
    <div class="mc-glass-card fade-in w-full p-6 md:p-8">
        <h1 class="text-fusion-pixel mb-4 text-2xl text-white">玩家登录</h1>
        <form id="loginForm" method="post" action="/auth/login">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <div style="margin-bottom:1rem;">
                <label for="username">用户名</label>
                <div class="input-group" style="margin-top:0.3rem;">
                    <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5zm0 2c-3.866 0-7 2.239-7 5v1h14v-1c0-2.761-3.134-5-7-5z"/>
                    </svg>
                    <input id="username" name="username" type="text" required class="custom-input" placeholder="请输入用户名" autocomplete="username">
                </div>
            </div>
            <div style="margin-bottom:1rem;">
                <label for="password">密码</label>
                <div class="input-group" style="margin-top:0.3rem;">
                    <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M17 8h-1V6c0-2.757-2.243-5-5-5S6 3.243 6 6v2H5c-1.103 0-2 .897-2 2v12c0 1.103.897 2 2 2h12c1.103 0 2-.897 2-2V10c0-1.103-.897-2-2-2zm-7-2c0-1.654 1.346-3 3-3s3 1.346 3 3v2H10V6zm7 16H5V10h12v12z"/>
                    </svg>
                    <input id="password" name="password" type="password" required class="custom-input" placeholder="请输入密码" autocomplete="current-password">
                </div>
            </div>
            <div style="margin-bottom:1rem;">
                <label for="loginCaptchaAnswer">图形验证码</label>
                <div style="margin-top:0.45rem;display:flex;flex-wrap:wrap;align-items:center;gap:0.75rem;">
                    <img id="loginCaptchaImage" src="/auth/captcha?purpose=login" alt="图形验证码" style="width:240px;height:64px;border-radius:0.85rem;border:1px solid rgba(103,232,249,.3);background:rgba(15,23,42,.9);object-fit:cover;">
                    <button type="button" id="loginCaptchaRefresh" class="auth-action-btn auth-action-btn--secondary rounded-xl border border-white/10 bg-slate-900/70 px-4 py-2 text-sm font-semibold text-slate-200 transition-all">
                        刷新验证码
                    </button>
                </div>
                <div class="input-group" style="margin-top:0.65rem;">
                    <input id="loginCaptchaAnswer" name="captcha_answer" type="text" required class="custom-input" placeholder="请输入图片中的计算结果" autocomplete="off" inputmode="numeric">
                </div>
            </div>
            <div class="auth-remember-row">
                <label class="auth-remember-label" for="remember">
                    <input type="checkbox" id="remember" name="remember" value="1" class="auth-remember-checkbox">
                    <span class="auth-remember-text">保持登录</span>
                </label>
            </div>
            <button type="submit" class="auth-action-btn auth-action-btn--primary mt-2 w-full rounded-xl border border-cyan-300/35 bg-cyan-500/20 py-3 text-base font-semibold text-cyan-200 transition-all sm:text-lg">
                登录
            </button>
            <a href="/auth/mua" class="auth-action-btn auth-action-btn--secondary mt-3 block w-full rounded-xl border border-white/10 bg-slate-900/70 py-3 text-center text-base font-semibold text-slate-200 transition-all sm:text-lg">
                🎮 使用 MUA 账号登录
            </a>
        </form>
        <p style="margin-top:1rem;font-size:0.9rem;color:#94a3b8;">
            还没有账号？<a href="/auth/register">前往注册</a>
            <span style="opacity:0.7;margin:0 0.35rem;">|</span>
            <a href="/forgot-password">忘记密码？</a>
        </p>
        <div id="loginMessage" style="margin-top:0.75rem;font-size:0.9rem;color:#cbd5e1;"></div>
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

document.getElementById('loginCaptchaRefresh').addEventListener('click', refreshLoginCaptcha);

document.getElementById('loginForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = this;
    const msgBox = document.getElementById('loginMessage');
    const btn = form.querySelector('button[type="submit"]');
    const originalText = btn ? btn.textContent.trim() : '登录';
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
        const data = await resp.json();
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
    }
});
</script>


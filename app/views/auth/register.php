<div class="page-container">
    <?php $oauthPendingEmail = trim((string)($oauthPendingEmail ?? ($_SESSION['oauth_pending_user']['email'] ?? ''))); ?>
    <?php $registerRequiresEmailCode = !empty($registerRequiresEmailCode); ?>
    <div class="mc-glass-card fade-in w-full p-6 md:p-8">
        <h1 class="text-fusion-pixel mb-4 text-2xl text-white">玩家注册</h1>
        <form id="registerForm" method="post" action="/auth/register" data-async-form="true">
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
            <div class="mb-4">
                <label class="mb-1 block text-sm text-slate-300" for="registerCaptchaAnswer">图形验证码</label>
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:0.75rem;">
                    <img id="registerCaptchaImage" src="/auth/captcha?purpose=<?= $registerRequiresEmailCode ? 'email_code' : 'register' ?>" alt="图形验证码" style="width:240px;height:64px;border-radius:0.85rem;border:1px solid rgba(103,232,249,.3);background:rgba(15,23,42,.9);object-fit:cover;">
                    <button type="button" id="registerCaptchaRefresh" class="auth-action-btn auth-action-btn--secondary rounded-xl border border-white/10 bg-slate-900/70 px-4 py-2 text-sm font-semibold text-slate-200 transition-all">
                        刷新验证码
                    </button>
                </div>
                <input id="registerCaptchaAnswer" name="captcha_answer" type="text" required class="custom-input custom-input--no-icon mt-3 w-full" placeholder="请输入图片中的计算结果" autocomplete="off" inputmode="numeric">
            </div>
            <?php if ($registerRequiresEmailCode): ?>
                <div class="mb-4">
                    <label class="mb-1 block text-sm text-slate-300" for="email_code">邮箱验证码</label>
                    <div style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;">
                        <input id="email_code" name="email_code" type="text" class="custom-input custom-input--no-icon w-full sm:flex-1" placeholder="请输入 6 位邮箱验证码" autocomplete="one-time-code" inputmode="numeric">
                        <button type="button" id="sendEmailCodeButton" class="auth-action-btn auth-action-btn--secondary rounded-xl border border-white/10 bg-slate-900/70 px-4 py-3 text-sm font-semibold text-slate-200 transition-all">
                            发送邮箱验证码
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">
                        发送验证码会消耗当前图形验证码。发送成功后，页面会刷新一张新的注册验证码，请重新填写后再提交注册。
                    </p>
                </div>
            <?php else: ?>
                <p class="mb-4 text-xs text-slate-400">
                    当前为 MUA 授权注册流程，邮箱验证码已豁免，但仍需完成图形验证码校验。
                </p>
            <?php endif; ?>
            <div style="display:none;">
                <input type="hidden" id="registerCaptchaPurpose" value="<?= $registerRequiresEmailCode ? 'email_code' : 'register' ?>">
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
const EMAIL_CODE_COOLDOWN_SECONDS = <?= (int)($registerEmailCodeCooldownSeconds ?? 60) ?>;
const REGISTER_REQUIRES_EMAIL_CODE = <?= $registerRequiresEmailCode ? 'true' : 'false' ?>;
let registerCaptchaPurpose = document.getElementById('registerCaptchaPurpose').value || 'register';
let registerEmailCodeSent = !REGISTER_REQUIRES_EMAIL_CODE;

function refreshRegisterCaptcha(nextPurpose) {
    if (nextPurpose) {
        registerCaptchaPurpose = nextPurpose;
        document.getElementById('registerCaptchaPurpose').value = nextPurpose;
    }
    const image = document.getElementById('registerCaptchaImage');
    const answer = document.getElementById('registerCaptchaAnswer');
    if (image) {
        image.src = '/auth/captcha?purpose=' + encodeURIComponent(registerCaptchaPurpose) + '&t=' + Date.now();
    }
    if (answer) {
        answer.value = '';
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
            return;
        }
        btn.textContent = `请稍候 (${remain}s)`;
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

document.getElementById('registerCaptchaRefresh').addEventListener('click', function () {
    refreshRegisterCaptcha();
});

const sendEmailCodeButton = document.getElementById('sendEmailCodeButton');
if (sendEmailCodeButton) {
    sendEmailCodeButton.addEventListener('click', async function () {
        const msgBox = document.getElementById('registerMessage');
        const emailEl = document.getElementById('email');
        const captchaEl = document.getElementById('registerCaptchaAnswer');
        const csrfEl = document.querySelector('#registerForm input[name="csrf_token"]');
        const originalText = sendEmailCodeButton.textContent.trim();
        hideGlobalLoadingIfPresent();

        if (!emailEl || !captchaEl || !csrfEl) {
            return;
        }
        if (!emailEl.value.trim()) {
            msgBox.textContent = '请先填写邮箱地址';
            return;
        }
        if (!captchaEl.value.trim()) {
            msgBox.textContent = '请先填写图形验证码';
            return;
        }

        sendEmailCodeButton.disabled = true;
        sendEmailCodeButton.textContent = '发送中...';
        msgBox.textContent = '正在发送邮箱验证码...';

        try {
            const formData = new FormData();
            formData.append('csrf_token', csrfEl.value);
            formData.append('email', emailEl.value.trim());
            formData.append('captcha_answer', captchaEl.value.trim());
            formData.append('purpose', 'register');

            const resp = await fetch('/auth/email-code/send', {
                method: 'POST',
                body: formData
            });
            const data = await readJsonSafe(resp);
            msgBox.textContent = data.message || (data.success ? '验证码已发送' : '验证码发送失败');

            if (data.success) {
                registerEmailCodeSent = true;
                refreshRegisterCaptcha('register');
                const emailCodeEl = document.getElementById('email_code');
                if (emailCodeEl) {
                    emailCodeEl.focus();
                }
                startButtonCooldown(sendEmailCodeButton, originalText, EMAIL_CODE_COOLDOWN_SECONDS);
                return;
            }

            sendEmailCodeButton.disabled = false;
            sendEmailCodeButton.textContent = originalText;
            refreshRegisterCaptcha('email_code');
        } catch (err) {
            console.error(err);
            msgBox.textContent = '网络错误，请稍后重试';
            sendEmailCodeButton.disabled = false;
            sendEmailCodeButton.textContent = originalText;
            refreshRegisterCaptcha('email_code');
        } finally {
            hideGlobalLoadingIfPresent();
        }
    });
}

document.getElementById('registerForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = this;
    const msgBox = document.getElementById('registerMessage');
    const btn = form.querySelector('button[type="submit"]');
    const originalText = btn ? btn.textContent.trim() : '注册';
    hideGlobalLoadingIfPresent();
    if (btn) {
        btn.disabled = true;
        btn.textContent = '正在提交...';
    }
    if (REGISTER_REQUIRES_EMAIL_CODE && !registerEmailCodeSent) {
        msgBox.textContent = '请先发送邮箱验证码';
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
        return;
    }
    msgBox.textContent = '正在提交注册...';
    try {
        const formData = new FormData(form);
        const resp = await fetch(form.action, {
            method: 'POST',
            body: formData
        });
        const data = await readJsonSafe(resp);
        msgBox.textContent = data.message || (data.success ? '注册成功' : '注册失败');
        if (resp.status === 429 || data.success) {
            form.reset();
            refreshRegisterCaptcha('register');
            if (btn) {
                startButtonCooldown(btn, originalText, AUTH_COOLDOWN_SECONDS);
            }
            return;
        }
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
        refreshRegisterCaptcha('register');
    } catch (err) {
        console.error(err);
        msgBox.textContent = '网络错误，请稍后重试';
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
        refreshRegisterCaptcha('register');
    } finally {
        hideGlobalLoadingIfPresent();
    }
});
</script>

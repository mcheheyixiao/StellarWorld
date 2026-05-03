<div class="page-container auth-page-shell">
    <?php $oauthPendingEmail = trim((string)($oauthPendingEmail ?? ($_SESSION['oauth_pending_user']['email'] ?? ''))); ?>
    <?php $oauthPendingProvider = trim((string)($oauthPendingProvider ?? ($_SESSION['oauth_pending_user']['provider'] ?? ''))); ?>
    <?php $oauthPendingMcUsername = trim((string)($oauthPendingMcUsername ?? ($_SESSION['oauth_pending_user']['mc_username'] ?? ''))); ?>
    <?php $isMicrosoftMinecraftPending = $oauthPendingProvider === 'microsoft_minecraft'; ?>
    <?php $registerRequiresEmailCode = !empty($registerRequiresEmailCode); ?>

    <div class="auth-card auth-card--wide fade-in">
        <div class="auth-card__brand">
            <img class="auth-card__logo-image" src="/images/email.png" alt="繁星World 图标" width="176" height="88">
            <h1 class="auth-card__title auth-card__title--center text-fusion-pixel">玩家注册</h1>
            <p class="auth-card__subtitle">创建你的繁星World账户</p>
        </div>

        <form id="registerForm" method="post" action="/auth/register" data-async-form="true" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <div class="auth-field">
                <label class="auth-label" for="username">用户名</label>
                <input id="username" name="username" type="text" required class="auth-input" autocomplete="username">
            </div>

            <div class="auth-field">
                <label class="auth-label" for="mc_username">绑定游戏名（可选）</label>
                <input id="mc_username" name="mc_username" type="text" class="auth-input"
                       value="<?= htmlspecialchars($isMicrosoftMinecraftPending ? $oauthPendingMcUsername : '', ENT_QUOTES, 'UTF-8') ?>"
                       <?= $isMicrosoftMinecraftPending ? 'readonly' : '' ?>
                       placeholder="仅填写游戏名，网站将自动生成 UUID" autocomplete="off">
                <?php if ($isMicrosoftMinecraftPending): ?>
                    <p class="auth-help-text">已通过 Microsoft 正版账号验证</p>
                <?php endif; ?>
            </div>

            <div class="auth-field">
                <label class="auth-label" for="email">邮箱</label>
                <input id="email" name="email" type="email" required class="auth-input"
                       value="<?= htmlspecialchars($oauthPendingEmail, ENT_QUOTES, 'UTF-8') ?>"
                       <?= $oauthPendingEmail !== '' ? 'readonly' : '' ?>
                       autocomplete="email">
                <p class="auth-help-text">当前仅支持 QQ / 网易 / Gmail / Outlook 等主流邮箱，暂不支持临时邮箱。</p>
            </div>

            <div class="auth-field">
                <label class="auth-label" for="password">密码</label>
                <input id="password" name="password" type="password" required class="auth-input" autocomplete="new-password">
            </div>

            <div class="auth-field">
                <label class="auth-label" for="registerCaptchaAnswer">图形验证码</label>
                <div class="auth-captcha-row">
                    <img id="registerCaptchaImage" class="auth-captcha-image" src="/auth/captcha?purpose=<?= $registerRequiresEmailCode ? 'email_code' : 'register' ?>" alt="图形验证码">
                    <button type="button" id="registerCaptchaRefresh" class="auth-button auth-button--secondary sm:w-auto">
                        刷新验证码
                    </button>
                </div>
                <input id="registerCaptchaAnswer" name="captcha_answer" type="text" required class="auth-input" placeholder="请输入图片中的计算结果" autocomplete="off" inputmode="numeric">
            </div>

            <?php if ($registerRequiresEmailCode): ?>
                <div class="auth-field">
                    <label class="auth-label" for="email_code">邮箱验证码</label>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <input id="email_code" name="email_code" type="text" class="auth-input w-full sm:flex-1" placeholder="请输入 6 位邮箱验证码" autocomplete="one-time-code" inputmode="numeric">
                        <button type="button" id="sendEmailCodeButton" class="auth-button auth-button--secondary w-full sm:w-auto">
                            发送邮箱验证码
                        </button>
                    </div>
                    <p class="auth-help-text">发送验证码会消耗当前图形验证码。发送成功后，页面会刷新一张新的注册验证码，请重新填写后再提交注册。</p>
                </div>
            <?php else: ?>
                <?php if ($oauthPendingProvider === 'mua'): ?>
                    <p class="auth-help-text">当前为 MUA 授权注册流程，邮箱验证码已豁免，但仍需完成图形验证码校验。</p>
                <?php else: ?>
                    <p class="auth-help-text">当前为授权注册流程，邮箱验证码已豁免，但仍需完成图形验证码校验。</p>
                <?php endif; ?>
            <?php endif; ?>

            <div style="display:none;">
                <input type="hidden" id="registerCaptchaPurpose" value="<?= $registerRequiresEmailCode ? 'email_code' : 'register' ?>">
            </div>

            <button type="submit" class="auth-button auth-button--primary">注册</button>
            <a href="/auth/mua" class="auth-button auth-button--mua">使用 MUA 账号快速注册</a>
            <?php if (defined('MICROSOFT_MINECRAFT_LOGIN_ENABLED') && MICROSOFT_MINECRAFT_LOGIN_ENABLED): ?>
                <a href="/auth/microsoft" class="auth-button auth-button--microsoft">使用 Microsoft 正版账号快速注册</a>
            <?php endif; ?>
        </form>

        <div class="auth-divider">已有账号</div>
        <p class="auth-link-row">前往 <a href="/auth/login">登录</a></p>
        <div id="registerMessage" class="auth-message"></div>
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
    btn.textContent = `Please wait (${remain}s)`;

    const timer = setInterval(() => {
        remain -= 1;
        if (remain <= 0) {
            clearInterval(timer);
            btn.disabled = false;
            btn.textContent = originalText;
            return;
        }
        btn.textContent = `Please wait (${remain}s)`;
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
            msgBox.textContent = 'Please enter your email first.';
            return;
        }
        if (!captchaEl.value.trim()) {
            msgBox.textContent = 'Please complete the captcha first.';
            return;
        }

        sendEmailCodeButton.disabled = true;
        sendEmailCodeButton.textContent = 'Sending...';
        msgBox.textContent = 'Sending verification code...';

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
            msgBox.textContent = data.message || (data.success ? 'Verification code sent.' : 'Failed to send verification code.');

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
            msgBox.textContent = 'Network error, please retry.';
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
    const originalText = btn ? btn.textContent.trim() : 'Register';
    hideGlobalLoadingIfPresent();
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Submitting...';
    }
    if (REGISTER_REQUIRES_EMAIL_CODE && !registerEmailCodeSent) {
        msgBox.textContent = 'Please send email verification code first.';
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
        return;
    }
    msgBox.textContent = 'Submitting registration...';
    try {
        const formData = new FormData(form);
        const resp = await fetch(form.action, {
            method: 'POST',
            body: formData
        });
        const data = await readJsonSafe(resp);
        msgBox.textContent = data.message || (data.success ? 'Registration succeeded.' : 'Registration failed.');
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
        msgBox.textContent = 'Network error, please retry.';
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

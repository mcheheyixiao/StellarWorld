<div class="page-container auth-page-shell">
    <div class="auth-card fade-in">
        <div class="auth-card__brand">
            <span class="auth-card__logo" aria-hidden="true">SW</span>
            <h1 class="auth-card__title text-fusion-pixel">重置密码</h1>
            <p class="auth-card__subtitle">设置一个新的登录密码，保护你的账户安全。</p>
        </div>

        <?php if (!empty($valid)): ?>
            <form id="resetForm" method="post" action="/reset-password" data-async-form="true" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars((string)($token ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                <div class="auth-field">
                    <label class="auth-label" for="password">新密码</label>
                    <input id="password" name="password" type="password" required class="auth-input" autocomplete="new-password">
                </div>

                <button type="submit" class="auth-button auth-button--primary">更新密码</button>
            </form>
        <?php else: ?>
            <div class="auth-message">
                <?= htmlspecialchars((string)($message ?? '重置链接无效或已过期'), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <p class="auth-link-row">
                需要重新发送？<a href="/forgot-password">返回找回密码</a>
            </p>
        <?php endif; ?>

        <div class="auth-divider">返回入口</div>
        <p class="auth-link-row"><a href="/auth/login">返回登录</a></p>
        <div id="resetMessage" class="auth-message"></div>
    </div>
</div>

<script>
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

const resetForm = document.getElementById('resetForm');
if (resetForm) {
    resetForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const form = this;
        const msgBox = document.getElementById('resetMessage');
        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn ? btn.textContent.trim() : '更新密码';
        hideGlobalLoadingIfPresent();
        if (btn) {
            btn.disabled = true;
            btn.textContent = '正在提交...';
        }
        msgBox.textContent = '正在更新密码...';
        try {
            const formData = new FormData(form);
            const resp = await fetch(form.action, {
                method: 'POST',
                body: formData
            });
            const data = await readJsonSafe(resp);
            msgBox.textContent = data.message || (data.success ? '更新成功' : '更新失败');
            if (data.success) {
                setTimeout(function () { window.location.href = '/auth/login'; }, 800);
                return;
            }
            if (btn) {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        } catch (err) {
            console.error(err);
            msgBox.textContent = '网络错误，请稍后重试';
            if (btn) {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        } finally {
            hideGlobalLoadingIfPresent();
        }
    });
}
</script>

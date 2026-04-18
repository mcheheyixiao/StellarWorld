<div class="page-container">
    <div class="mc-glass-card fade-in w-full p-6 md:p-8">
        <h1 class="text-fusion-pixel mb-4 text-2xl text-white">重置密码</h1>

        <?php if (!empty($valid)): ?>
            <form id="resetForm" method="post" action="/reset-password">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars((string)($token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <div style="margin-bottom:1rem;">
                    <label for="password">新密码</label>
                    <input id="password" name="password" type="password" required
                           style="width:100%;margin-top:0.3rem;padding:0.6rem 0.8rem;border-radius:.7rem;border:1px solid rgba(148,163,184,.3);background:rgba(15,23,42,.72);color:#e2e8f0;">
                </div>
                <button type="submit" class="btn" style="width:100%;margin-top:0.5rem;border-radius:.75rem;background:rgba(14,165,233,.32);border:1px solid rgba(34,211,238,.4);color:#e0f2fe;">
                    更新密码
                </button>
            </form>
        <?php else: ?>
            <div style="color:#94a3b8;font-size:0.95rem;line-height:1.6;">
                <?= htmlspecialchars((string)($message ?? '重置链接无效或已过期'), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <p style="margin-top:1rem;font-size:0.9rem;color:#94a3b8;">
                需要重新发送？<a href="/forgot-password">返回找回密码</a>
            </p>
        <?php endif; ?>

        <p style="margin-top:1rem;font-size:0.9rem;color:#94a3b8;">
            <a href="/auth/login">返回登录</a>
        </p>
        <div id="resetMessage" style="margin-top:0.75rem;font-size:0.9rem;color:#cbd5e1;"></div>
    </div>
</div>

<script>
const resetForm = document.getElementById('resetForm');
if (resetForm) {
    resetForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const form = this;
        const msgBox = document.getElementById('resetMessage');
        msgBox.textContent = '正在更新密码...';
        try {
            const formData = new FormData(form);
            const resp = await fetch(form.action, {
                method: 'POST',
                body: formData
            });
            const data = await resp.json();
            msgBox.textContent = data.message || (data.success ? '更新成功' : '更新失败');
            if (data.success) {
                setTimeout(function () { window.location.href = '/auth/login'; }, 800);
            }
        } catch (err) {
            console.error(err);
            msgBox.textContent = '网络错误，请稍后重试';
        }
    });
}
</script>


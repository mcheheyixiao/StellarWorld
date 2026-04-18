<?php
/** @var array $profile */
/** @var bool $canChangeMcName */
/** @var string $cooldownMessage */
/** @var string $emailMasked */
/** @var string|null $muaSkinUrl */

$profile = $profile ?? [];
$canChangeMcName = (bool)($canChangeMcName ?? false);
$cooldownMessage = (string)($cooldownMessage ?? '');
$emailMasked = (string)($emailMasked ?? '未绑定邮箱');
$muaSkinUrl = $muaSkinUrl ?? null;

$mcUsername = trim((string)($profile['mc_username'] ?? ''));
$hasMcName = $mcUsername !== '';
$renderName = $hasMcName ? $mcUsername : 'MHF_Steve';
$minotarBodyUrl = 'https://minotar.net/armor/body/' . rawurlencode($renderName) . '/300.png';
$craftheadSkinUrl = 'https://crafthead.net/skin/' . rawurlencode($renderName);
$muaSub = trim((string)($profile['mua_sub'] ?? ''));
$isMuaBound = $muaSub !== '';
$useMuaSkinRender = $isMuaBound && is_string($muaSkinUrl) && trim($muaSkinUrl) !== '';
$finalRawSkinUrl = $useMuaSkinRender ? (string)$muaSkinUrl : $craftheadSkinUrl;

$roleRaw = (string)($profile['role'] ?? 'player');
$roleLabel = $roleRaw === 'admin' ? '管理员' : '玩家';

$createdAtRaw = (string)($profile['created_at'] ?? '');
$createdAtDisplay = '—';
if ($createdAtRaw !== '') {
    $ts = strtotime($createdAtRaw);
    $createdAtDisplay = $ts !== false ? date('Y-m-d', $ts) : $createdAtRaw;
}
?>

<style>
.profile-page {
    --profile-surface: rgba(2, 6, 23, 0.72);
    --profile-surface-soft: rgba(15, 23, 42, 0.72);
    --profile-border: rgba(148, 163, 184, 0.22);
    --profile-border-soft: rgba(148, 163, 184, 0.28);
    --profile-shadow: 0 22px 60px -34px rgba(0, 0, 0, 0.8);
    --profile-text-strong: #f8fafc;
    --profile-text-body: #cbd5e1;
    --profile-text-muted: #94a3b8;
    --profile-accent: #67e8f9;
    --profile-btn-bg: rgba(14, 165, 233, 0.24);
    --profile-btn-bg-hover: rgba(14, 165, 233, 0.36);
    --profile-btn-border: rgba(34, 211, 238, 0.35);
    --profile-btn-text: #e0f2fe;
    --profile-secondary-bg: rgba(15, 23, 42, 0.72);
    --profile-secondary-border: rgba(148, 163, 184, 0.3);
    --profile-secondary-text: #e2e8f0;
    --profile-meta-border: rgba(148, 163, 184, 0.3);
    max-width: 1200px;
    margin: 0 auto;
    padding: 1rem 0.75rem 2.5rem;
}
[data-theme="light"] .profile-page {
    --profile-surface: rgba(255, 255, 255, 0.86);
    --profile-surface-soft: rgba(255, 255, 255, 0.95);
    --profile-border: rgba(148, 163, 184, 0.38);
    --profile-border-soft: rgba(148, 163, 184, 0.45);
    --profile-shadow: 0 16px 34px -24px rgba(15, 23, 42, 0.28);
    --profile-text-strong: #0f172a;
    --profile-text-body: #334155;
    --profile-text-muted: #475569;
    --profile-accent: #0e7490;
    --profile-btn-bg: rgba(14, 165, 233, 0.15);
    --profile-btn-bg-hover: rgba(14, 165, 233, 0.22);
    --profile-btn-border: rgba(14, 116, 144, 0.45);
    --profile-btn-text: #0c4a6e;
    --profile-secondary-bg: rgba(255, 255, 255, 0.95);
    --profile-secondary-border: rgba(148, 163, 184, 0.5);
    --profile-secondary-text: #0f172a;
    --profile-meta-border: rgba(148, 163, 184, 0.45);
}
.profile-dashboard {
    display: grid;
    grid-template-columns: minmax(0, 320px) minmax(0, 1fr);
    gap: 1rem;
}
.profile-sidebar {
    position: sticky;
    top: 6.5rem;
    border: 1px solid var(--profile-border);
    border-radius: 1.5rem;
    background: var(--profile-surface);
    backdrop-filter: blur(20px);
    box-shadow: var(--profile-shadow);
    padding: 1rem;
    text-align: center;
}
.profile-avatar-wrap {
    border-radius: 1rem;
    border: 1px solid var(--profile-border);
    background: var(--profile-surface-soft);
    min-height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.profile-avatar-wrap img,
.profile-avatar-wrap canvas {
    width: 100%;
    max-width: 220px;
    height: auto;
    display: block;
}
.profile-username {
    margin: 0.85rem 0 0;
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--profile-text-strong);
}
.profile-role-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.65rem;
    padding: 0.32rem 0.8rem;
    border-radius: 9999px;
    border: 1px solid rgba(34, 211, 238, 0.35);
    background: rgba(14, 165, 233, 0.16);
    color: #a5f3fc;
    font-size: 0.75rem;
    font-weight: 700;
}
.profile-role-badge.admin {
    border-color: rgba(245, 158, 11, 0.4);
    background: rgba(245, 158, 11, 0.18);
    color: #fcd34d;
}
.profile-meta {
    margin-top: 0.95rem;
    border-top: 1px dashed var(--profile-meta-border);
    padding-top: 0.85rem;
    font-size: 0.85rem;
    color: var(--profile-text-body);
    line-height: 1.8;
}
.profile-meta strong { color: var(--profile-text-strong); }
.profile-unbound-hint { margin-top: 0.45rem; font-size: 0.78rem; color: var(--profile-text-muted); line-height: 1.5; }
.profile-content-stack { display: flex; flex-direction: column; gap: 1rem; }
.profile-card {
    border: 1px solid var(--profile-border);
    border-radius: 1.5rem;
    background: var(--profile-surface);
    backdrop-filter: blur(20px);
    box-shadow: var(--profile-shadow);
    padding: 1.15rem;
}
.profile-card h2 { margin-top: 0; display: flex; align-items: center; gap: 0.5rem; color: var(--profile-text-strong); }
.profile-card h2 i { color: var(--profile-accent); }
.profile-card p { color: var(--profile-text-muted); margin-top: 0.3rem; }
.profile-input {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--profile-border-soft);
    border-radius: 0.7rem;
    background: var(--profile-surface-soft);
    color: var(--profile-text-strong);
}
.profile-input::placeholder {
    color: var(--profile-text-muted);
}
.profile-btn {
    border-radius: 0.75rem;
    border: 1px solid var(--profile-btn-border);
    background: var(--profile-btn-bg);
    color: var(--profile-btn-text);
}
.profile-btn:hover { background: var(--profile-btn-bg-hover); }
[data-theme="light"] .profile-card p[style*="color:#fca5a5"] {
    color: #b91c1c !important;
}
[data-theme="light"] .profile-card a[href="/auth/mua"],
[data-theme="light"] #quick-reset-btn {
    border-color: var(--profile-secondary-border) !important;
    background: var(--profile-secondary-bg) !important;
    color: var(--profile-secondary-text) !important;
}
[data-theme="light"] .profile-card a[href="/auth/mua"]:hover,
[data-theme="light"] #quick-reset-btn:hover {
    background: rgba(224, 242, 254, 0.9) !important;
    border-color: rgba(14, 116, 144, 0.38) !important;
    color: #0c4a6e !important;
}
[data-theme="light"] .profile-card strong[style*="color:#10b981"] {
    color: #047857 !important;
}
[data-theme="light"] .profile-card strong[style*="color:#f59e0b"] {
    color: #b45309 !important;
}
[data-theme="light"] .profile-content-stack > .profile-card div[style*="border-top:1px dashed var(--glass-border)"] {
    border-top-color: var(--profile-meta-border) !important;
}
@media (max-width: 900px) {
    .profile-dashboard { grid-template-columns: 1fr; }
    .profile-sidebar { position: static; top: auto; }
}
</style>

<div class="profile-page">
    <div class="profile-dashboard">
        <aside class="profile-sidebar">
            <div class="profile-avatar-wrap"<?= $hasMcName ? '' : ' aria-hidden="true"' ?>>
                <canvas id="skin_container_3d" style="outline:none; display:none;"></canvas>
                <img id="skin_container_2d" src="<?= htmlspecialchars($minotarBodyUrl, ENT_QUOTES, 'UTF-8') ?>" alt="玩家角色" width="220" height="293" loading="eager" decoding="async">
            </div>
            <h1 class="profile-username"><?= htmlspecialchars((string)($profile['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
            <span class="profile-role-badge <?= $roleRaw === 'admin' ? 'admin' : '' ?>">
                <i class="mdi mdi-shield-account"></i>
                <?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <div class="profile-meta">
                注册时间：<strong><?= htmlspecialchars($createdAtDisplay, ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <?php if (!$hasMcName): ?>
                <p class="profile-unbound-hint">尚未绑定游戏 UUID，当前为 Steve 占位图；绑定角色后将显示你的形象。</p>
            <?php endif; ?>
        </aside>

        <div class="profile-content-stack">
            <section class="profile-card">
                <h2 style="margin-top:0;display:flex;align-items:center;gap:0.5rem;">
                    <i class="mdi mdi-calendar-check"></i>
                    <span>每日签到福利</span>
                </h2>
                <p style="color:var(--color-text-muted);margin-top:0.3rem;">
                    每日在网页签到，即可获得积分或游戏内奖励。
                </p>
                <div style="margin-top:1rem;">
                    <button
                        class="btn profile-btn"
                        type="button"
                        onclick="alert('签到系统正在开发中，敬请期待！')"
                    >
                        立即签到
                    </button>
                </div>
            </section>

            <section class="profile-card">
                <h2 style="margin-top:0;display:flex;align-items:center;gap:0.5rem;">
                    <i class="mdi mdi-minecraft"></i>
                    <span>游戏角色设置</span>
                </h2>
                <p style="color:var(--color-text-muted);margin-top:0.3rem;">
                    当前绑定：<strong><?= htmlspecialchars((string)($profile['mc_username'] ?? '未绑定'), ENT_QUOTES, 'UTF-8') ?></strong>
                </p>
                <?php if (!$canChangeMcName): ?>
                    <p style="color:#fca5a5;font-weight:600;margin:0.6rem 0 0.9rem;"><?= htmlspecialchars($cooldownMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form id="mc-bind-form" method="post" action="/profile/mc-character/update">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <label for="mc-name-input" style="display:block;margin-bottom:0.35rem;">Minecraft Username</label>
                    <input
                        id="mc-name-input"
                        name="mc_name"
                        type="text"
                        value="<?= htmlspecialchars((string)($profile['mc_username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        <?= $canChangeMcName ? '' : 'readonly' ?>
                        required
                        class="profile-input"
                    >
                    <button
                        type="submit"
                        class="btn profile-btn"
                        <?= $canChangeMcName ? '' : 'disabled' ?>
                        style="width:100%;margin-top:0.9rem;<?= $canChangeMcName ? '' : 'opacity:0.6;cursor:not-allowed;' ?>"
                    >
                        保存角色绑定
                    </button>
                </form>
            </section>

            <section class="profile-card">
                <h2 style="margin-top:0;display:flex;align-items:center;gap:0.5rem;">
                    <i class="mdi mdi-account-link"></i>
                    <span>MUA Union 账号</span>
                </h2>
                <p style="color:var(--color-text-muted);margin-top:0.3rem;">
                    绑定状态：
                    <?php if ($isMuaBound): ?>
                        <strong style="color:#10b981;">已绑定</strong>
                    <?php else: ?>
                        <strong style="color:#f59e0b;">未绑定</strong>
                        <a href="/auth/mua" class="btn" style="margin-left:0.7rem;padding:0.35rem 0.75rem;border:1px solid rgba(148,163,184,.3);background:rgba(15,23,42,.72);color:#e2e8f0;border-radius:.65rem;">去绑定</a>
                    <?php endif; ?>
                </p>
            </section>

            <section class="profile-card">
                <h2 style="margin-top:0;display:flex;align-items:center;gap:0.5rem;">
                    <i class="mdi mdi-lock-reset"></i>
                    <span>安全与密码</span>
                </h2>
                <form id="password-form" method="post" action="/profile/password/update">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <label for="old-password-input" style="display:block;margin-bottom:0.35rem;">旧密码</label>
                    <input
                        id="old-password-input"
                        name="old_password"
                        type="password"
                        required
                        class="profile-input"
                        style="margin-bottom:0.8rem;"
                    >
                    <label for="new-password-input" style="display:block;margin-bottom:0.35rem;">新密码</label>
                    <input
                        id="new-password-input"
                        name="new_password"
                        type="password"
                        minlength="6"
                        required
                        class="profile-input"
                    >
                    <button type="submit" class="btn profile-btn" style="width:100%;margin-top:0.9rem;">更新密码</button>
                </form>

                <div style="margin-top:1rem;padding-top:0.9rem;border-top:1px dashed var(--glass-border);">
                    <button id="quick-reset-btn" type="button" class="btn" style="width:100%;font-size:0.9rem;border:1px solid rgba(148,163,184,.3);background:rgba(15,23,42,.72);color:#e2e8f0;border-radius:.75rem;">
                        忘记了旧密码？向绑定的邮箱 (<?= htmlspecialchars($emailMasked, ENT_QUOTES, 'UTF-8') ?>) 发送重置链接
                    </button>
                </div>
            </section>
        </div>
    </div>
</div>

<script src="https://unpkg.com/skinview3d@3.4.1/bundles/skinview3d.bundle.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById("skin_container_3d");
    const img2d = document.getElementById("skin_container_2d");
    const rawSkinUrl = "<?= htmlspecialchars((string)$finalRawSkinUrl, ENT_QUOTES, 'UTF-8') ?>";

    // If the user bound MUA, keep the proxy to avoid cross-origin/CORS issues.
    const isMua = <?= $useMuaSkinRender ? 'true' : 'false' ?>;
    const finalSkinUrl = isMua
        ? ("/api/skin-proxy?url=" + encodeURIComponent(rawSkinUrl))
        : rawSkinUrl;

    if (!canvas || !img2d || typeof skinview3d === 'undefined') {
        return;
    }

    // Progressive enhancement: show 2D immediately; upgrade to 3D when it loads.
    canvas.style.display = "none";
    img2d.style.display = "block";

    const skinViewer = new skinview3d.SkinViewer({
        canvas: canvas,
        width: 220,
        height: 293
    });

    try {
        skinViewer.loadSkin(finalSkinUrl)
            .then(() => {
                canvas.style.display = "block";
                img2d.style.display = "none";
                if (skinViewer.animations) {
                    skinViewer.animations.add(new skinview3d.IdleAnimation());
                }
            })
            .catch((err) => {
                console.warn("3D Skin load failed, falling back to 2D", err);
                canvas.style.display = "none";
                img2d.style.display = "block";
            });
    } catch (err) {
        console.warn("skinview3d loadSkin threw an exception, falling back to 2D", err);
        canvas.style.display = "none";
        img2d.style.display = "block";
    }
});
</script>
<script>
(() => {
    const parseJson = async (res) => {
        try {
            return await res.json();
        } catch (e) {
            return { success: false, message: '响应解析失败' };
        }
    };

    const submitForm = async (form, url) => {
        const formData = new FormData(form);
        const res = await fetch(url, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        return parseJson(res);
    };

    const mcForm = document.getElementById('mc-bind-form');
    if (mcForm) {
        mcForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const result = await submitForm(mcForm, '/profile/mc-character/update');
            alert(result.message || (result.success ? '操作成功' : '操作失败'));
            if (result.success) {
                window.location.reload();
            }
        });
    }

    const pwdForm = document.getElementById('password-form');
    if (pwdForm) {
        pwdForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const result = await submitForm(pwdForm, '/profile/password/update');
            alert(result.message || (result.success ? '操作成功' : '操作失败'));
            if (result.success) {
                pwdForm.reset();
            }
        });
    }

    const quickResetBtn = document.getElementById('quick-reset-btn');
    if (quickResetBtn) {
                quickResetBtn.addEventListener('click', async () => {
            quickResetBtn.disabled = true;
            try {
                const fd = new FormData();
                const csrf = document.querySelector('#password-form input[name="csrf_token"]');
                if (csrf) fd.append('csrf_token', csrf.value);
                const res = await fetch('/profile/password/quick-reset', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                });
                const result = await parseJson(res);
                alert(result.message || (result.success ? '邮件发送成功' : '发送失败'));
            } finally {
                setTimeout(() => {
                    quickResetBtn.disabled = false;
                }, 800);
            }
        });
    }
})();
</script>

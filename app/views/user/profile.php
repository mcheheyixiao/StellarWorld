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
$minotarAvatarUrl = 'https://minotar.net/helm/' . rawurlencode($renderName) . '/96.png';
$craftheadSkinUrl = 'https://crafthead.net/skin/' . rawurlencode($renderName);
$muaSub = trim((string)($profile['mua_sub'] ?? ''));
$isMuaBound = $muaSub !== '';
$useMuaSkinRender = $isMuaBound && is_string($muaSkinUrl) && trim($muaSkinUrl) !== '';
$finalRawSkinUrl = $useMuaSkinRender ? (string)$muaSkinUrl : $craftheadSkinUrl;

$roleRaw = (string)($profile['role'] ?? 'player');
$roleLabel = $roleRaw === 'admin' ? '管理员' : '玩家';

$createdAtRaw = (string)($profile['created_at'] ?? '');
$createdAtDisplay = '--';
if ($createdAtRaw !== '') {
    $ts = strtotime($createdAtRaw);
    $createdAtDisplay = $ts !== false ? date('Y-m-d', $ts) : $createdAtRaw;
}

$qqValue = trim((string)($profile['qq'] ?? ($profile['qq_number'] ?? '')));
$uidValue = isset($profile['id']) ? (string)$profile['id'] : '';
$qqOrUidDisplay = $qqValue !== '' ? ('QQ ' . $qqValue) : ($uidValue !== '' ? ('UID #' . $uidValue) : '--');

$gameAccountCountDisplay = '--';
if (isset($profile['game_accounts_count']) && $profile['game_accounts_count'] !== '') {
    $gameAccountCountDisplay = (string)$profile['game_accounts_count'];
} elseif (isset($profile['mc_account_count']) && $profile['mc_account_count'] !== '') {
    $gameAccountCountDisplay = (string)$profile['mc_account_count'];
} elseif ($hasMcName) {
    $gameAccountCountDisplay = '1';
}

$onlineDisplay = '--';
if (isset($profile['online_count']) && $profile['online_count'] !== '') {
    $onlineDisplay = (string)$profile['online_count'];
} elseif (isset($profile['current_online']) && $profile['current_online'] !== '') {
    $onlineDisplay = (string)$profile['current_online'];
} elseif (isset($profile['is_online']) && $profile['is_online'] !== '') {
    $onlineDisplay = ((int)$profile['is_online'] === 1) ? '在线' : '离线';
}

$durationDisplay = '--';
if (isset($profile['total_play_time']) && $profile['total_play_time'] !== '') {
    $durationDisplay = (string)$profile['total_play_time'];
} elseif (isset($profile['play_time']) && $profile['play_time'] !== '') {
    $durationDisplay = (string)$profile['play_time'];
} elseif (isset($profile['total_duration']) && $profile['total_duration'] !== '') {
    $durationDisplay = (string)$profile['total_duration'];
}

$deathDisplay = '--';
if (isset($profile['death_count']) && $profile['death_count'] !== '') {
    $deathDisplay = (string)$profile['death_count'];
} elseif (isset($profile['deaths']) && $profile['deaths'] !== '') {
    $deathDisplay = (string)$profile['deaths'];
}
?>

<style>
.profile-shell {
    display: grid;
    grid-template-columns: 1fr;
}
.profile-sidebar {
    border-bottom: 1px solid rgba(51, 65, 85, 0.8);
}
.duration-200 {
    transition-duration: 200ms;
}
.ease-in-out {
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}
.bg-gray-100 {
    background-color: #f3f4f6 !important;
}
.text-gray-700 {
    color: #374151 !important;
}
[data-theme="dark"] .dark\:bg-gray-800 {
    background-color: #1f2937 !important;
}
[data-theme="dark"] .dark\:text-gray-300 {
    color: #d1d5db !important;
}
@media (hover: hover) {
    .hover\:bg-blue-600:hover {
        background-color: #2563eb !important;
    }
    .hover\:bg-slate-700\/80:hover {
        background-color: rgba(51, 65, 85, 0.8) !important;
    }
    [data-theme="light"] .hover\:bg-slate-700\/80:hover {
        background-color: rgba(226, 232, 240, 0.9) !important;
    }
    .hover\:bg-gray-200:hover {
        background-color: #e5e7eb !important;
    }
    [data-theme="dark"] .dark\:hover\:bg-gray-700:hover {
        background-color: #374151 !important;
    }
}
@media (min-width: 1024px) {
    .profile-shell {
        grid-template-columns: 280px minmax(0, 1fr);
        min-height: calc(100vh - 9rem);
    }
    .profile-sidebar {
        border-bottom: 0;
        border-right: 1px solid rgba(51, 65, 85, 0.8);
    }
}
</style>

<div class="max-w-7xl mx-auto p-4 md:p-6">
    <div class="overflow-hidden rounded-2xl border border-slate-700/70 bg-slate-900/85 shadow-2xl backdrop-blur">
        <div class="profile-shell">
            <aside class="profile-sidebar p-4 md:p-5 flex flex-col gap-4">
                <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl shadow-lg p-6 text-center text-white">
                    <img
                        src="<?= htmlspecialchars($minotarAvatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                        alt="用户头像"
                        width="96"
                        height="96"
                        class="rounded-full w-24 h-24 mx-auto border-4 border-white/40 object-cover"
                        loading="eager"
                        decoding="async"
                    >
                    <h1 class="mt-3 text-2xl font-bold"><?= htmlspecialchars((string)($profile['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="mt-1 text-blue-100"><?= htmlspecialchars($qqOrUidDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                    <span class="mt-3 inline-flex items-center rounded-full bg-white/20 px-3 py-1 text-xs font-semibold"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <p class="mt-3 text-sm text-blue-50">注册时间：<?= htmlspecialchars($createdAtDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                </div>

                <nav class="grid grid-cols-1 gap-2">
                    <button type="button" data-profile-tab-btn data-target="panel-game" class="profile-nav-btn rounded-xl bg-blue-500 px-4 py-3 text-base font-medium text-white shadow transition-all duration-200 ease-in-out hover:-translate-y-0.5 hover:bg-blue-600 hover:shadow-md">游戏数据</button>
                    <button type="button" data-profile-tab-btn data-target="panel-bind" class="profile-nav-btn rounded-xl bg-slate-800/70 px-4 py-3 text-base font-medium text-slate-200 transition-all duration-200 ease-in-out hover:-translate-y-0.5 hover:bg-slate-700/80 hover:shadow-md">账号绑定</button>
                    <button type="button" data-profile-tab-btn data-target="panel-security" class="profile-nav-btn rounded-xl bg-slate-800/70 px-4 py-3 text-base font-medium text-slate-200 transition-all duration-200 ease-in-out hover:-translate-y-0.5 hover:bg-slate-700/80 hover:shadow-md">安全中心</button>
                    <button type="button" data-profile-tab-btn data-target="panel-player" class="profile-nav-btn rounded-xl bg-slate-800/70 px-4 py-3 text-base font-medium text-slate-200 transition-all duration-200 ease-in-out hover:-translate-y-0.5 hover:bg-slate-700/80 hover:shadow-md">玩家功能</button>
                </nav>

                <div class="mt-auto rounded-xl border border-slate-700/80 bg-slate-800/60 p-3 text-xs text-slate-300">
                     
                </div>
            </aside>

            <main class="flex min-w-0 flex-col gap-4 p-4 md:p-6">
                <div class="rounded-xl border border-slate-700/80 bg-slate-800/60 p-4">
                    <h2 class="text-lg font-semibold text-slate-100">个人仪表盘</h2>
                </div>

                <section class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div class="rounded-xl border border-slate-700/80 bg-slate-800/70 p-4 transition hover:-translate-y-0.5 hover:shadow-md">
                        <p class="text-sm text-slate-400">游戏账号数量</p>
                        <p class="mt-1 text-2xl font-bold text-slate-100"><?= htmlspecialchars($gameAccountCountDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="rounded-xl border border-slate-700/80 bg-slate-800/70 p-4 transition hover:-translate-y-0.5 hover:shadow-md">
                        <p class="text-sm text-slate-400">当前在线</p>
                        <p class="mt-1 text-2xl font-bold text-slate-100"><?= htmlspecialchars($onlineDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="rounded-xl border border-slate-700/80 bg-slate-800/70 p-4 transition hover:-translate-y-0.5 hover:shadow-md">
                        <p class="text-sm text-slate-400">累计时长</p>
                        <p class="mt-1 text-2xl font-bold text-slate-100"><?= htmlspecialchars($durationDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </section>

                <div class="min-h-0 flex-1">
                    <section id="panel-game" data-profile-panel class="rounded-xl border border-slate-700/80 bg-slate-800/60 p-6">
                        <h3 class="text-lg font-semibold text-slate-100">A. 游戏数据</h3>
                        <p class="mt-1 text-sm text-slate-400">游戏账号信息、在线状态、累计时长与死亡次数。</p>

                        <div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-[1.4fr_1fr]">
                            <div class="space-y-3">
                                <div class="rounded-xl border border-slate-700/80 bg-slate-900/50 p-4">
                                    <p class="text-sm text-slate-400">游戏账号信息</p>
                                    <p class="mt-1 font-semibold text-slate-100"><?= htmlspecialchars((string)($profile['mc_username'] ?? '未绑定'), ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="mt-1 text-xs text-slate-400">UUID：<?= htmlspecialchars((string)($profile['mc_uuid'] ?? '--'), ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <div class="rounded-xl border border-slate-700/80 bg-slate-900/50 p-4">
                                        <p class="text-xs text-slate-400">在线状态</p>
                                        <p class="mt-1 text-lg font-semibold text-slate-100"><?= htmlspecialchars($onlineDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div class="rounded-xl border border-slate-700/80 bg-slate-900/50 p-4">
                                        <p class="text-xs text-slate-400">累计时长</p>
                                        <p class="mt-1 text-lg font-semibold text-slate-100"><?= htmlspecialchars($durationDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                    <div class="rounded-xl border border-slate-700/80 bg-slate-900/50 p-4">
                                        <p class="text-xs text-slate-400">死亡次数</p>
                                        <p class="mt-1 text-lg font-semibold text-slate-100"><?= htmlspecialchars($deathDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                                    </div>
                                </div>
                                <?php if (!$hasMcName): ?>
                                    <p class="text-sm text-amber-400">未绑定游戏 UUID，当前显示 Steve 占位形象；绑定后自动更新。</p>
                                <?php endif; ?>
                            </div>

                            <div class="rounded-xl border border-slate-700/80 bg-slate-900/50 p-4 flex flex-col items-center justify-center">
                                <canvas id="skin_container_3d" class="w-full max-w-xs mx-auto"></canvas>
                                <img id="skin_container_2d" src="<?= htmlspecialchars($minotarBodyUrl, ENT_QUOTES, 'UTF-8') ?>" alt="玩家角色" class="w-full max-w-xs mx-auto" width="220" height="293" loading="eager" decoding="async">
                            </div>
                        </div>
                    </section>

                    <section id="panel-bind" data-profile-panel class="rounded-xl border border-slate-700/80 bg-slate-800/60 p-6">
                        <h3 class="text-lg font-semibold text-slate-100">B. 账号绑定</h3>
                        <p class="mt-1 text-sm text-slate-400">Minecraft 与 Union 绑定管理。</p>

                        <div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-2">
                            <div class="rounded-xl border border-slate-700/80 bg-slate-900/50 p-4">
                                <h4 class="font-semibold text-slate-100">Minecraft 绑定</h4>
                                <p class="mt-1 text-sm text-slate-400">当前绑定：<?= htmlspecialchars((string)($profile['mc_username'] ?? '未绑定'), ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if (!$canChangeMcName): ?>
                                    <p class="mt-2 text-sm font-semibold text-red-400"><?= htmlspecialchars($cooldownMessage, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>

                                <form id="mc-bind-form" method="post" action="/profile/mc-character/update" class="mt-4 space-y-3">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <label for="mc-name-input" class="block text-sm text-slate-300">Minecraft Username</label>
                                    <input
                                        id="mc-name-input"
                                        name="mc_name"
                                        type="text"
                                        value="<?= htmlspecialchars((string)($profile['mc_username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $canChangeMcName ? '' : 'readonly' ?>
                                        required
                                        class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-400"
                                    >
                                    <button
                                        type="submit"
                                        class="w-full rounded-lg bg-blue-500 px-4 py-2 font-medium text-white transition hover:bg-blue-500 transition-all duration-200 ease-in-out hover:bg-blue-600 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-60"
                                        <?= $canChangeMcName ? '' : 'disabled' ?>
                                    >
                                        保存角色绑定
                                    </button>
                                </form>
                            </div>

                            <div class="rounded-xl border border-slate-700/80 bg-slate-900/50 p-4">
                                <h4 class="font-semibold text-slate-100">Union 绑定</h4>
                                <p class="mt-2 text-sm text-slate-400">
                                    绑定状态：
                                    <?php if ($isMuaBound): ?>
                                        <strong class="text-emerald-400">已绑定</strong>
                                    <?php else: ?>
                                        <strong class="text-amber-400">未绑定</strong>
                                    <?php endif; ?>
                                </p>
                                <?php if (!$isMuaBound): ?>
                                    <a href="/auth/mua" class="mt-4 inline-flex rounded-lg bg-blue-500 px-4 py-2 font-medium text-white transition hover:bg-blue-500 transition-all duration-200 ease-in-out hover:bg-blue-600 hover:shadow-md">去绑定</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <section id="panel-security" data-profile-panel class="rounded-xl border border-slate-700/80 bg-slate-800/60 p-6">
                        <h3 class="text-lg font-semibold text-slate-100">C. 安全中心</h3>
                        <p class="mt-1 text-sm text-slate-400">修改密码与邮箱快速重置。</p>

                        <form id="password-form" method="post" action="/profile/password/update" class="mt-5 space-y-4 rounded-xl border border-slate-700/80 bg-slate-900/50 p-4">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <div>
                                <label for="old-password-input" class="block text-sm text-slate-300">旧密码</label>
                                <input
                                    id="old-password-input"
                                    name="old_password"
                                    type="password"
                                    required
                                    class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-400"
                                >
                            </div>
                            <div>
                                <label for="new-password-input" class="block text-sm text-slate-300">新密码</label>
                                <input
                                    id="new-password-input"
                                    name="new_password"
                                    type="password"
                                    minlength="6"
                                    required
                                    class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-400"
                                >
                            </div>
                            <button type="submit" class="w-full rounded-lg bg-blue-500 px-4 py-2 font-medium text-white transition hover:bg-blue-500 transition-all duration-200 ease-in-out hover:bg-blue-600 hover:shadow-md">更新密码</button>
                        </form>

                        <div class="mt-4 rounded-xl border border-slate-700/80 bg-slate-900/50 p-4">
                            <button id="quick-reset-btn" type="button" class="w-full rounded-lg bg-blue-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-500 transition-all duration-200 ease-in-out hover:bg-blue-600 hover:shadow-md">
                                忘记旧密码？向绑定邮箱 (<?= htmlspecialchars($emailMasked, ENT_QUOTES, 'UTF-8') ?>) 发送重置链接
                            </button>
                        </div>
                    </section>

                    <section id="panel-player" data-profile-panel class="rounded-xl border border-slate-700/80 bg-slate-800/60 p-6">
                        <h3 class="text-lg font-semibold text-slate-100">D. 玩家功能</h3>
                        <p class="mt-1 text-sm text-slate-400">每日签到领取积分或游戏内奖励。</p>

                        <div class="mt-5 rounded-xl border border-slate-700/80 bg-slate-900/50 p-4">
                            <button
                                type="button"
                                class="rounded-lg bg-blue-500 px-4 py-2 font-medium text-white transition hover:bg-blue-500 transition-all duration-200 ease-in-out hover:bg-blue-600 hover:shadow-md"
                                onclick="alert('签到系统正在开发中，敬请期待！')"
                            >
                                立即签到
                            </button>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>
</div>

<script src="https://unpkg.com/skinview3d@3.4.1/bundles/skinview3d.bundle.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('skin_container_3d');
    const img2d = document.getElementById('skin_container_2d');
    const rawSkinUrl = "<?= htmlspecialchars((string)$finalRawSkinUrl, ENT_QUOTES, 'UTF-8') ?>";

    const isMua = <?= $useMuaSkinRender ? 'true' : 'false' ?>;
    const finalSkinUrl = isMua
        ? ('/api/skin-proxy?url=' + encodeURIComponent(rawSkinUrl))
        : rawSkinUrl;

    if (!canvas || !img2d || typeof skinview3d === 'undefined') {
        return;
    }

    canvas.style.display = 'none';
    img2d.style.display = 'block';

    const skinViewer = new skinview3d.SkinViewer({
        canvas: canvas,
        width: 220,
        height: 293
    });

    try {
        skinViewer.loadSkin(finalSkinUrl)
            .then(() => {
                canvas.style.display = 'block';
                img2d.style.display = 'none';
                if (skinViewer.animations) {
                    skinViewer.animations.add(new skinview3d.IdleAnimation());
                }
            })
            .catch((err) => {
                console.warn('3D Skin load failed, falling back to 2D', err);
                canvas.style.display = 'none';
                img2d.style.display = 'block';
            });
    } catch (err) {
        console.warn('skinview3d loadSkin threw an exception, falling back to 2D', err);
        canvas.style.display = 'none';
        img2d.style.display = 'block';
    }
});
</script>
<script>
(() => {
    const navButtons = document.querySelectorAll('[data-profile-tab-btn]');
    const panels = document.querySelectorAll('[data-profile-panel]');

    const setActiveTab = (targetId) => {
        panels.forEach((panel) => {
            if (panel.id === targetId) {
                panel.style.display = 'block';
            } else {
                panel.style.display = 'none';
            }
        });

        navButtons.forEach((btn) => {
            const isActive = btn.getAttribute('data-target') === targetId;
            if (isActive) {
                btn.classList.remove('bg-slate-800/70', 'text-slate-200');
                btn.classList.add('bg-blue-500', 'text-white', 'shadow');
            } else {
                btn.classList.remove('bg-blue-500', 'text-white', 'shadow');
                btn.classList.add('bg-slate-800/70', 'text-slate-200');
            }
        });
    };

    navButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            if (targetId) {
                setActiveTab(targetId);
            }
        });
    });

    panels.forEach((panel) => {
        panel.style.display = 'none';
    });
    setActiveTab('panel-game');
})();
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

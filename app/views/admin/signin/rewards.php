<?php
$editorState = isset($signinRewardEditorState) && is_array($signinRewardEditorState) ? $signinRewardEditorState : [];
$draftConfig = isset($editorState['draft']) && is_array($editorState['draft']) ? $editorState['draft'] : null;
$activeConfig = isset($editorState['active']) && is_array($editorState['active']) ? $editorState['active'] : null;
$scheduledConfig = isset($editorState['scheduled']) && is_array($editorState['scheduled']) ? $editorState['scheduled'] : null;
$flatRules = isset($editorState['flat_rules']) && is_array($editorState['flat_rules']) ? $editorState['flat_rules'] : [];
$settings = isset($editorState['settings']) && is_array($editorState['settings']) ? $editorState['settings'] : [];
$draftName = $draftConfig !== null ? (string)($draftConfig['name'] ?? '签到奖励草稿') : '签到奖励草稿';

if ($flatRules === []) {
    $flatRules[] = [
        'id' => 0,
        'rule_type' => 'daily',
        'trigger_day' => 1,
        'mail_title' => '每日签到奖励',
        'mail_icon' => 'BOOK',
        'mail_content' => [
            '你完成了 {date} 的每日签到。',
            '连续签到：{continuous} 天',
            '累计签到：{total} 次',
        ],
        'items' => [
            ['type' => 'minecraft:diamond', 'amount' => 1],
        ],
        'commands' => [],
        'enabled' => true,
        'sort_order' => 0,
    ];
}

$csrfToken = htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$isSaved = isset($_GET['saved']) && (string)$_GET['saved'] === '1';
$isPublished = isset($_GET['published']) && (string)$_GET['published'] === '1';
$isTestSent = isset($_GET['test_sent']) && (string)$_GET['test_sent'] === '1';
$isDeleted = isset($_GET['deleted']) && (string)$_GET['deleted'] === '1';
$errCode = trim((string)($_GET['err'] ?? ''));
$effectiveDateFlash = trim((string)($_GET['effective_date'] ?? ''));

$activeLabel = $activeConfig !== null
    ? ('#' . (int)($activeConfig['id'] ?? 0) . ' / ' . htmlspecialchars((string)($activeConfig['name'] ?? ''), ENT_QUOTES, 'UTF-8'))
    : '当前无数据库生效配置（将回退 SIGNIN_REWARD_* 常量）';
$scheduledLabel = $scheduledConfig !== null
    ? ('#' . (int)($scheduledConfig['id'] ?? 0) . ' / ' . htmlspecialchars((string)($scheduledConfig['name'] ?? ''), ENT_QUOTES, 'UTF-8'))
    : '当前无待生效配置';
$draftLabel = $draftConfig !== null
    ? ('#' . (int)($draftConfig['id'] ?? 0) . ' / ' . htmlspecialchars((string)($draftConfig['name'] ?? ''), ENT_QUOTES, 'UTF-8'))
    : '草稿不可用';

$bootstrap = [
    'rules' => $flatRules,
    'settings' => [
        'test_send_enabled' => (int)($settings['test_send_enabled'] ?? 1),
        'admin_repeat_test_enabled' => (int)($settings['admin_repeat_test_enabled'] ?? 0),
    ],
    'defaultServerId' => defined('SIGNIN_SERVER_ID') ? (string)SIGNIN_SERVER_ID : 'survival-1',
];
?>

<div id="signin-rewards-page" class="space-y-6">
    <div class="ta-card">
        <h1>SweetMail 签到奖励配置</h1>
        <p class="ta-help-text mt-2">
            可随时保存草稿。发布后默认次日生效，因此不会影响今天玩家签到奖励。
        </p>
        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
            <div class="rounded-lg border border-slate-300/25 p-3">
                <p class="text-xs uppercase tracking-wide ta-help-text">当前生效</p>
                <p class="mt-1 text-sm"><?= $activeLabel ?></p>
                <?php if ($activeConfig !== null && !empty($activeConfig['effective_date'])): ?>
                    <p class="mt-1 text-xs ta-help-text">生效日期：<?= htmlspecialchars((string)$activeConfig['effective_date'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
            <div class="rounded-lg border border-slate-300/25 p-3">
                <p class="text-xs uppercase tracking-wide ta-help-text">下次生效</p>
                <p class="mt-1 text-sm"><?= $scheduledLabel ?></p>
                <?php if ($scheduledConfig !== null && !empty($scheduledConfig['effective_date'])): ?>
                    <p class="mt-1 text-xs ta-help-text">生效日期：<?= htmlspecialchars((string)$scheduledConfig['effective_date'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
            <div class="rounded-lg border border-slate-300/25 p-3">
                <p class="text-xs uppercase tracking-wide ta-help-text">草稿</p>
                <p class="mt-1 text-sm"><?= $draftLabel ?></p>
                <?php if ($draftConfig !== null && !empty($draftConfig['updated_at'])): ?>
                    <p class="mt-1 text-xs ta-help-text">更新时间：<?= htmlspecialchars((string)$draftConfig['updated_at'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="mt-4 rounded-lg border border-sky-300/25 bg-sky-500/10 p-3 text-sm">
            默认发布策略：次日 00:00 生效（生效日期 = <?= htmlspecialchars($tomorrow, ENT_QUOTES, 'UTF-8') ?>）。
        </div>
    </div>

    <?php if ($isSaved): ?>
        <div class="ta-card border-emerald-300/30 bg-emerald-500/10 text-emerald-100">草稿已保存。</div>
    <?php endif; ?>
    <?php if ($isPublished): ?>
        <div class="ta-card border-emerald-300/30 bg-emerald-500/10 text-emerald-100">
            草稿已发布。<?= $effectiveDateFlash !== '' ? ' 生效日期：' . htmlspecialchars($effectiveDateFlash, ENT_QUOTES, 'UTF-8') . '。' : '' ?>
        </div>
    <?php endif; ?>
    <?php if ($isTestSent): ?>
        <div class="ta-card border-emerald-300/30 bg-emerald-500/10 text-emerald-100">测试奖励已写入 `stellar_reward_outbox` 队列。</div>
    <?php endif; ?>
    <?php if ($isDeleted): ?>
        <div class="ta-card border-emerald-300/30 bg-emerald-500/10 text-emerald-100">已从草稿删除该规则。</div>
    <?php endif; ?>
    <?php if ($errCode !== ''): ?>
        <div class="ta-card border-rose-300/30 bg-rose-500/10 text-rose-100">操作失败：<?= htmlspecialchars($errCode, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form id="signin-rewards-save-form" class="ta-card space-y-4" method="post" action="/admin/signin-rewards/save-draft">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" id="signin-rewards-payload-json" name="payload_json" value="">

        <div class="flex flex-wrap gap-2">
            <button type="button" class="ta-btn ta-btn-secondary" data-add-rule="daily">新增每日奖励</button>
            <button type="button" class="ta-btn ta-btn-secondary" data-add-rule="streak">新增连续奖励</button>
            <button type="button" class="ta-btn ta-btn-secondary" data-add-rule="total">新增累计奖励</button>
            <button type="button" class="ta-btn ta-btn-secondary" data-add-rule="monthly">新增本月奖励</button>
            <button type="submit" class="ta-btn ta-btn-primary">保存草稿</button>
        </div>

        <label class="block">
            <span class="text-sm">草稿名称</span>
            <input type="text" id="signin-draft-name" value="<?= htmlspecialchars($draftName, ENT_QUOTES, 'UTF-8') ?>" maxlength="120">
        </label>

        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="admin_repeat_test_enabled" value="1" <?= ((int)($settings['admin_repeat_test_enabled'] ?? 0) === 1) ? 'checked' : '' ?>>
            <span>允许管理员重复模拟正式签到（预留功能，默认关闭）</span>
        </label>

        <div id="signin-reward-cards" class="space-y-4"></div>
    </form>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="ta-card space-y-3">
            <h2 class="text-base font-semibold">预览</h2>
            <p class="ta-help-text">预览基于当前草稿，最终校验与 payload 清洗以后端保存/发送结果为准。</p>
            <div>
                <p class="text-sm font-medium">邮件预览</p>
                <pre id="signin-preview-mail" class="mt-2 overflow-auto rounded-lg border border-slate-300/25 p-3 text-xs"></pre>
            </div>
            <div>
                <p class="text-sm font-medium">物品预览（已合并）</p>
                <pre id="signin-preview-items" class="mt-2 overflow-auto rounded-lg border border-slate-300/25 p-3 text-xs"></pre>
            </div>
            <div>
                <p class="text-sm font-medium">命令预览（已去重）</p>
                <pre id="signin-preview-commands" class="mt-2 overflow-auto rounded-lg border border-slate-300/25 p-3 text-xs"></pre>
            </div>
            <div>
                <p class="text-sm font-medium">最终 Payload JSON</p>
                <pre id="signin-preview-payload" class="mt-2 max-h-80 overflow-auto rounded-lg border border-slate-300/25 p-3 text-xs"></pre>
            </div>
        </div>

        <div class="space-y-6">
            <form class="ta-card space-y-3" method="post" action="/admin/signin-rewards/publish">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <h2 class="text-base font-semibold">发布</h2>
                <p class="ta-help-text">默认将草稿发布为“明日生效”的正式配置。</p>
                <label class="block">
                    <span class="text-sm">生效日期</span>
                    <input type="date" name="effective_date" value="<?= htmlspecialchars($tomorrow, ENT_QUOTES, 'UTF-8') ?>">
                </label>
                <button type="submit" class="ta-btn ta-btn-primary">发布到该生效日期</button>
            </form>

            <form id="signin-rewards-test-form" class="ta-card space-y-3" method="post" action="/admin/signin-rewards/test-send">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <h2 class="text-base font-semibold">测试发送</h2>
                <p class="ta-help-text">
                    开关状态：<?= ((int)($settings['test_send_enabled'] ?? 1) === 1) ? '已开启' : '已关闭' ?>。
                    测试发送会写入 `source=signin_test`，不会改动正式签到统计或缓存。
                </p>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <label class="block">
                        <span class="text-sm">玩家名</span>
                        <input type="text" name="target_player_name" placeholder="默认：当前管理员绑定的玩家">
                    </label>
                    <label class="block">
                        <span class="text-sm">玩家 UUID</span>
                        <input type="text" name="target_player_uuid" placeholder="可选：当玩家名可解析时可留空">
                    </label>
                    <label class="block">
                        <span class="text-sm">连续签到天数</span>
                        <input type="number" name="continuous" min="1" value="1">
                    </label>
                    <label class="block">
                        <span class="text-sm">累计签到次数</span>
                        <input type="number" name="total" min="1" value="1">
                    </label>
                    <label class="block">
                        <span class="text-sm">本月签到天数</span>
                        <input type="number" name="month_days" min="1" value="1">
                    </label>
                    <label class="block">
                        <span class="text-sm">签到日期</span>
                        <input type="date" name="sign_date" value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
                    </label>
                    <label class="block md:col-span-2">
                        <span class="text-sm">服务器 ID</span>
                        <input type="text" name="server_id" value="<?= htmlspecialchars(defined('SIGNIN_SERVER_ID') ? (string)SIGNIN_SERVER_ID : 'survival-1', ENT_QUOTES, 'UTF-8') ?>">
                    </label>
                </div>
                <button type="submit" class="ta-btn ta-btn-primary">发送测试奖励</button>
            </form>
        </div>
    </div>
</div>

<script>
window.signinRewardsBootstrap = <?= json_encode($bootstrap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

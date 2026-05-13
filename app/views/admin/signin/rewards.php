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
$tomorrowTs = strtotime('tomorrow');
$tomorrow = $tomorrowTs !== false ? date('Y-m-d', $tomorrowTs) : date('Y-m-d', time() + 86400);

$isSaved = isset($_GET['saved']) && (string)$_GET['saved'] === '1';
$isPublished = isset($_GET['published']) && (string)$_GET['published'] === '1';
$isTestSent = isset($_GET['test_sent']) && (string)$_GET['test_sent'] === '1';
$isDeleted = isset($_GET['deleted']) && (string)$_GET['deleted'] === '1';
$errCode = trim((string)($_GET['err'] ?? ''));
$effectiveDateFlash = trim((string)($_GET['effective_date'] ?? ''));
$errMessageFlash = trim((string)($_GET['err_msg'] ?? ''));

$errCodeMessages = [
    'invalid_payload' => '请求数据无效，请刷新页面后重试。',
    'save_draft' => '保存草稿失败，请稍后重试。',
    'publish' => '发布失败，请检查配置后重试。',
    'publish_date_too_early' => '正式配置最早只能从明天 00:00 生效；当天验证请使用测试发送。',
    'delete_rule' => '删除规则失败，请稍后重试。',
    'test_send' => '测试发送失败，请检查输入后重试。',
];
if ($errCode !== '' && $errMessageFlash === '') {
    $errMessageFlash = $errCodeMessages[$errCode] ?? ('操作失败：' . $errCode);
}

$activeLabel = $activeConfig !== null
    ? ('#' . (int)($activeConfig['id'] ?? 0) . ' / ' . htmlspecialchars((string)($activeConfig['name'] ?? ''), ENT_QUOTES, 'UTF-8'))
    : '当前无数据库生效配置（将回退 SIGNIN_REWARD_* 常量）';
$scheduledLabel = $scheduledConfig !== null
    ? ('#' . (int)($scheduledConfig['id'] ?? 0) . ' / ' . htmlspecialchars((string)($scheduledConfig['name'] ?? ''), ENT_QUOTES, 'UTF-8'))
    : '当前无待生效配置';
$draftLabel = $draftConfig !== null
    ? ('#' . (int)($draftConfig['id'] ?? 0) . ' / ' . htmlspecialchars((string)($draftConfig['name'] ?? ''), ENT_QUOTES, 'UTF-8'))
    : '草稿不可用';

$testSendEnabled = (int)($settings['test_send_enabled'] ?? 1) === 1;
$repeatTestEnabled = (int)($settings['admin_repeat_test_enabled'] ?? 0) === 1;

$bootstrap = [
    'rules' => $flatRules,
    'settings' => [
        'test_send_enabled' => (int)($settings['test_send_enabled'] ?? 1),
        'admin_repeat_test_enabled' => (int)($settings['admin_repeat_test_enabled'] ?? 0),
    ],
    'defaultServerId' => defined('SIGNIN_SERVER_ID') ? (string)SIGNIN_SERVER_ID : 'survival-1',
    'defaultRuleTab' => 'daily',
];
?>

<style>
#signin-rewards-page .signin-rw-status-grid {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(1, minmax(0, 1fr));
}
@media (min-width: 768px) {
    #signin-rewards-page .signin-rw-status-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (min-width: 1280px) {
    #signin-rewards-page .signin-rw-status-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}
#signin-rewards-page .signin-rw-status-card {
    display: flex;
    gap: 0.7rem;
    align-items: flex-start;
    border-radius: 0.9rem;
    border: 1px solid var(--ta-border);
    background: rgba(148, 163, 184, 0.08);
    padding: 0.85rem;
    box-shadow: 0 12px 22px -18px rgba(15, 23, 42, 0.65);
}
[data-theme="light"] #signin-rewards-page .signin-rw-status-card {
    background: rgba(241, 245, 249, 0.92);
}
#signin-rewards-page .signin-rw-status-icon {
    width: 1.9rem;
    height: 1.9rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: rgba(59, 130, 246, 0.14);
    color: #60a5fa;
}
#signin-rewards-page .signin-rw-status-body {
    min-width: 0;
}
#signin-rewards-page .signin-rw-status-card.interactive {
    transition: border-color .2s ease, box-shadow .2s ease;
}
#signin-rewards-page .signin-rw-status-card.interactive:hover {
    border-color: rgba(59, 130, 246, 0.5);
    box-shadow: 0 12px 24px -18px rgba(59, 130, 246, 0.75);
}
#signin-rewards-page .signin-rw-status-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.86rem;
    font-weight: 600;
}
#signin-rewards-page .signin-rw-badge {
    font-size: 0.7rem;
    line-height: 1;
    border-radius: 999px;
    border: 1px solid rgba(148, 163, 184, 0.45);
    padding: 0.22rem 0.45rem;
    white-space: nowrap;
}
#signin-rewards-page .signin-rw-badge.ok {
    border-color: rgba(34, 197, 94, 0.5);
    color: #22c55e;
    background: rgba(34, 197, 94, 0.14);
}
#signin-rewards-page .signin-rw-badge.pending {
    border-color: rgba(59, 130, 246, 0.5);
    color: #60a5fa;
    background: rgba(59, 130, 246, 0.15);
}
#signin-rewards-page .signin-rw-badge.warn {
    border-color: rgba(245, 158, 11, 0.5);
    color: #f59e0b;
    background: rgba(245, 158, 11, 0.14);
}
#signin-rewards-page .signin-rw-badge.off {
    border-color: rgba(148, 163, 184, 0.5);
    color: var(--ta-text-muted);
    background: rgba(148, 163, 184, 0.12);
}
#signin-rewards-page .signin-rw-status-note {
    margin-top: 0.25rem;
    font-size: 0.76rem;
    line-height: 1.35;
    color: var(--ta-text-muted);
    word-break: break-word;
}
#signin-rewards-page .signin-rw-status-action {
    margin-top: 0.35rem;
    border: 0;
    background: transparent;
    padding: 0;
    font-size: 0.74rem;
    color: #60a5fa;
    cursor: pointer;
}
#signin-rewards-page .signin-rw-status-action:hover {
    color: #93c5fd;
}
#signin-rewards-page .signin-rw-status-help {
    margin-top: 0.3rem;
    font-size: 0.74rem;
    line-height: 1.35;
    color: var(--ta-text-muted);
}
#signin-rewards-page .signin-rw-info-bar {
    border-radius: 0.82rem;
    border: 1px solid rgba(59, 130, 246, 0.32);
    background: rgba(59, 130, 246, 0.1);
    padding: 0.62rem 0.78rem;
    font-size: 0.82rem;
}
#signin-rewards-page .signin-rw-main-grid {
    display: grid;
    gap: 1rem;
}
@media (min-width: 1280px) {
    #signin-rewards-page .signin-rw-main-grid {
        grid-template-columns: minmax(0, 1.7fr) minmax(0, 1fr);
        align-items: start;
    }
}
#signin-rewards-page .signin-rw-side-stack {
    display: grid;
    gap: 0.9rem;
}
@media (min-width: 1280px) {
    #signin-rewards-page .signin-rw-side-stack {
        position: sticky;
        top: 5.7rem;
    }
}
#signin-rewards-page .signin-rw-editor-head {
    display: grid;
    gap: 0.7rem;
    grid-template-columns: 1fr;
}
@media (min-width: 768px) {
    #signin-rewards-page .signin-rw-editor-head {
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: end;
    }
}
#signin-rewards-page .signin-rw-tabbar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
    align-items: center;
    justify-content: space-between;
}
#signin-rewards-page .signin-rw-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
#signin-rewards-page .signin-rw-tab {
    border: 1px solid var(--ta-border-strong);
    border-radius: 0.66rem;
    padding: 0.48rem 0.76rem;
    font-size: 0.82rem;
    font-weight: 600;
    background: rgba(148, 163, 184, 0.08);
    color: var(--ta-text-body);
}
#signin-rewards-page .signin-rw-tab.active {
    border-color: rgba(37, 99, 235, 0.58);
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.9), rgba(14, 165, 233, 0.85));
    color: #ffffff;
}
#signin-rewards-page .signin-rw-card {
    border: 1px solid var(--ta-border);
    border-radius: 0.9rem;
    padding: 0.9rem;
    background: rgba(148, 163, 184, 0.05);
}
#signin-rewards-page .signin-rw-card-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}
#signin-rewards-page .signin-rw-card-block {
    margin-top: 0.75rem;
    border: 1px solid rgba(148, 163, 184, 0.25);
    border-radius: 0.8rem;
    padding: 0.72rem;
    background: rgba(148, 163, 184, 0.04);
}
[data-theme="light"] #signin-rewards-page .signin-rw-card,
[data-theme="light"] #signin-rewards-page .signin-rw-card-block {
    background: rgba(248, 250, 252, 0.86);
}
#signin-rewards-page .signin-rw-block-title {
    margin: 0 0 0.52rem;
    font-size: 0.84rem;
    font-weight: 700;
}
#signin-rewards-page .signin-rw-hint {
    margin-top: 0.38rem;
    font-size: 0.74rem;
    line-height: 1.35;
    color: var(--ta-text-muted);
}
#signin-rewards-page .signin-rw-inline-grid {
    display: grid;
    gap: 0.5rem;
    grid-template-columns: 1fr;
}
@media (min-width: 768px) {
    #signin-rewards-page .signin-rw-inline-grid.items {
        grid-template-columns: 6fr 2fr 2fr;
        align-items: center;
    }
    #signin-rewards-page .signin-rw-inline-grid.commands {
        grid-template-columns: 8fr 2fr;
        align-items: center;
    }
}
#signin-rewards-page .signin-rw-advanced {
    border: 1px dashed rgba(148, 163, 184, 0.35);
    border-radius: 0.78rem;
    padding: 0.65rem 0.72rem;
    background: rgba(148, 163, 184, 0.04);
}
#signin-rewards-page .signin-rw-advanced > summary {
    cursor: pointer;
    font-size: 0.83rem;
    font-weight: 600;
    list-style: none;
}
#signin-rewards-page .signin-rw-advanced > summary::-webkit-details-marker {
    display: none;
}
#signin-rewards-page .signin-rw-preview-box {
    max-height: 10.5rem;
    overflow: auto;
    margin-top: 0.4rem;
    border-radius: 0.68rem;
    border: 1px solid rgba(148, 163, 184, 0.26);
    padding: 0.6rem;
    font-size: 0.74rem;
    line-height: 1.45;
}
#signin-rewards-page .signin-rw-payload-details > summary {
    cursor: pointer;
    font-size: 0.83rem;
    font-weight: 600;
}
#signin-rewards-page .signin-rw-empty {
    border: 1px dashed rgba(148, 163, 184, 0.4);
    border-radius: 0.8rem;
    padding: 0.85rem;
    text-align: center;
    color: var(--ta-text-muted);
    font-size: 0.82rem;
}
</style>

<div id="signin-rewards-page" class="space-y-5 signin-rw-page">
    <section class="ta-card space-y-4">
        <div>
            <h1>SweetMail 签到奖励配置</h1>
            <p class="ta-help-text mt-2">
                可随时保存草稿。发布后默认次日生效，因此不会影响今天玩家签到奖励。
            </p>
        </div>

        <div class="signin-rw-status-grid">
            <article class="signin-rw-status-card">
                <span class="signin-rw-status-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4"><path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <div class="signin-rw-status-body">
                    <div class="signin-rw-status-title">
                        <span>当前生效</span>
                        <span class="signin-rw-badge <?= $activeConfig !== null ? 'ok' : 'off' ?>"><?= $activeConfig !== null ? '生效中' : '未设置' ?></span>
                    </div>
                    <p class="signin-rw-status-note"><?= $activeLabel ?></p>
                    <button type="button" class="signin-rw-status-action" data-toggle-status-help="signin-status-help-active">查看说明</button>
                    <p id="signin-status-help-active" class="signin-rw-status-help hidden">普通玩家今天签到实际使用的配置。若无数据库生效配置，则回退 SIGNIN_REWARD_* 常量。</p>
                </div>
            </article>

            <article class="signin-rw-status-card">
                <span class="signin-rw-status-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4"><path d="M12 8v4l2.5 2.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <div class="signin-rw-status-body">
                    <div class="signin-rw-status-title">
                        <span>下次生效</span>
                        <span class="signin-rw-badge <?= $scheduledConfig !== null ? 'pending' : 'off' ?>"><?= $scheduledConfig !== null ? '已排期' : '待发布' ?></span>
                    </div>
                    <p class="signin-rw-status-note"><?= $scheduledLabel ?></p>
                    <button type="button" class="signin-rw-status-action" data-toggle-status-help="signin-status-help-scheduled">查看说明</button>
                    <p id="signin-status-help-scheduled" class="signin-rw-status-help hidden">这里展示已发布并等待生效的配置。到达生效日期后，会自动切换为当前生效配置。</p>
                </div>
            </article>

            <article class="signin-rw-status-card interactive" data-scroll-target="signin-rewards-save-form">
                <span class="signin-rw-status-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4"><path d="M12 4v16m8-8H4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </span>
                <div class="signin-rw-status-body">
                    <div class="signin-rw-status-title">
                        <span>草稿</span>
                        <span class="signin-rw-badge warn">可编辑</span>
                    </div>
                    <p class="signin-rw-status-note"><?= $draftLabel ?></p>
                    <p class="signin-rw-status-help">保存草稿不会影响玩家签到，需要发布后才会排期生效。</p>
                    <button type="button" class="signin-rw-status-action" data-scroll-target="signin-rewards-save-form">滚动到规则编辑器</button>
                </div>
            </article>

            <article class="signin-rw-status-card interactive" data-scroll-target="signin-rewards-test-form">
                <span class="signin-rw-status-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4"><path d="M12 4a2 2 0 0 1 2 2v1.3a6 6 0 0 1 2.7 2.7H18a2 2 0 1 1 0 4h-1.3a6 6 0 0 1-2.7 2.7V18a2 2 0 1 1-4 0v-1.3a6 6 0 0 1-2.7-2.7H6a2 2 0 1 1 0-4h1.3A6 6 0 0 1 10 7.3V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.5"/></svg>
                </span>
                <div class="signin-rw-status-body">
                    <div class="signin-rw-status-title">
                        <span>测试模式</span>
                        <span id="signin-status-test-send-badge" class="signin-rw-badge <?= $testSendEnabled ? 'ok' : 'off' ?>"><?= $testSendEnabled ? '已开启' : '已关闭' ?></span>
                    </div>
                    <p class="signin-rw-status-note">测试发送用于验证奖励，不影响正式签到统计。</p>
                    <p class="signin-rw-status-help">重复正式签到测试：<span id="signin-status-repeat-test-state"><?= $repeatTestEnabled ? '已开启' : '默认关闭' ?></span></p>
                    <button type="button" class="signin-rw-status-action" data-scroll-target="signin-rewards-test-form">滚动到测试发送卡片</button>
                </div>
            </article>
        </div>

        <div class="signin-rw-info-bar">
            正式配置最早次日 00:00 生效（当前最早可选：<?= htmlspecialchars($tomorrow, ENT_QUOTES, 'UTF-8') ?>）。
        </div>
    </section>

    <?php if ($isSaved): ?>
        <div class="ta-card border-emerald-300/35 bg-emerald-500/10 text-emerald-100">草稿已保存，但不会影响普通玩家签到。请点击“发布到生效日期”后，配置才会在指定日期生效。</div>
    <?php endif; ?>
    <?php if ($isPublished): ?>
        <?php $publishedDate = $effectiveDateFlash !== '' ? $effectiveDateFlash : $tomorrow; ?>
        <div class="ta-card border-emerald-300/35 bg-emerald-500/10 text-emerald-100">
            配置已发布并排期，将于 <?= htmlspecialchars($publishedDate, ENT_QUOTES, 'UTF-8') ?> 00:00 后成为当前生效配置。
        </div>
    <?php endif; ?>
    <?php if ($isTestSent): ?>
        <div class="ta-card border-emerald-300/35 bg-emerald-500/10 text-emerald-100">测试奖励已入队，source=signin_test，不会影响正式签到统计。</div>
    <?php endif; ?>
    <?php if ($isDeleted): ?>
        <div class="ta-card border-emerald-300/35 bg-emerald-500/10 text-emerald-100">规则已从草稿中删除。</div>
    <?php endif; ?>
    <?php if ($errCode !== ''): ?>
        <div class="ta-card border-rose-300/35 bg-rose-500/10 text-rose-100"><?= htmlspecialchars($errMessageFlash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="signin-rw-main-grid">
        <section class="space-y-4">
            <form id="signin-rewards-save-form" class="ta-card space-y-4 signin-rw-editor-card" method="post" action="/admin/signin-rewards/save-draft">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" id="signin-rewards-payload-json" name="payload_json" value="">

                <div class="signin-rw-editor-head">
                    <label class="block">
                        <span class="text-sm">草稿名称</span>
                        <input type="text" id="signin-draft-name" value="<?= htmlspecialchars($draftName, ENT_QUOTES, 'UTF-8') ?>" maxlength="120">
                    </label>
                    <button type="submit" class="ta-btn ta-btn-primary">保存草稿</button>
                </div>

                <div class="signin-rw-tabbar">
                    <div id="signin-rule-tabs" class="signin-rw-tabs" role="tablist" aria-label="规则分类">
                        <button type="button" class="signin-rw-tab active" data-rule-tab="daily">每日基础</button>
                        <button type="button" class="signin-rw-tab" data-rule-tab="streak">连续签到</button>
                        <button type="button" class="signin-rw-tab" data-rule-tab="total">累计签到</button>
                        <button type="button" class="signin-rw-tab" data-rule-tab="monthly">本月签到</button>
                    </div>
                    <button type="button" id="signin-add-current-tab" class="ta-btn ta-btn-secondary">新增当前分类规则</button>
                </div>

                <div id="signin-reward-cards" class="space-y-3"></div>
            </form>
        </section>

        <aside class="signin-rw-side-col">
            <div class="signin-rw-side-stack">
                <section class="ta-card space-y-3">
                    <h2 class="text-base font-semibold mt-0">实时预览</h2>
                    <div>
                        <p class="text-sm font-medium">邮件预览</p>
                        <pre id="signin-preview-mail" class="signin-rw-preview-box"></pre>
                    </div>
                    <div>
                        <p class="text-sm font-medium">物品预览</p>
                        <pre id="signin-preview-items" class="signin-rw-preview-box"></pre>
                    </div>
                    <div>
                        <p class="text-sm font-medium">命令预览</p>
                        <pre id="signin-preview-commands" class="signin-rw-preview-box"></pre>
                    </div>
                    <details class="signin-rw-payload-details">
                        <summary>Payload JSON（预览）</summary>
                        <pre id="signin-preview-payload" class="signin-rw-preview-box"></pre>
                    </details>
                </section>

                <form class="ta-card space-y-3" method="post" action="/admin/signin-rewards/publish">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <h2 class="text-base font-semibold mt-0">发布</h2>
                    <p class="ta-help-text">正式配置最早次日 00:00 生效；当天验证请使用测试发送。</p>
                    <label class="block">
                        <span class="text-sm">生效日期</span>
                        <input type="date" name="effective_date" value="<?= htmlspecialchars($tomorrow, ENT_QUOTES, 'UTF-8') ?>" min="<?= htmlspecialchars($tomorrow, ENT_QUOTES, 'UTF-8') ?>">
                    </label>
                    <button type="submit" class="ta-btn ta-btn-primary w-full">发布到生效日期</button>
                </form>

                <form id="signin-rewards-test-form" class="ta-card space-y-3" method="post" action="/admin/signin-rewards/test-send">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <h2 class="text-base font-semibold mt-0">测试发送</h2>
                    <p class="ta-help-text">
                        测试发送写入 `source=signin_test`，不会影响正式签到统计。
                    </p>
                    <div id="signin-test-mode-settings" class="signin-rw-card-block space-y-2">
                        <p class="signin-rw-block-title mb-0">测试模式开关</p>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="signin_reward_test_send_enabled" value="1" <?= $testSendEnabled ? 'checked' : '' ?>>
                            <span>启用测试发送（控制是否允许写入 source=signin_test）</span>
                        </label>
                        <p class="signin-rw-hint">默认开启。关闭后测试发送会被后端拒绝，不会写入测试奖励。</p>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="admin_repeat_test_enabled" value="1" <?= $repeatTestEnabled ? 'checked' : '' ?>>
                            <span>重复正式签到测试（预留/高风险，默认关闭）</span>
                        </label>
                        <p class="signin-rw-hint">该开关与“测试发送开关”不同，请勿混用。</p>
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <label class="block md:col-span-2">
                            <span class="text-sm">玩家名</span>
                            <input type="text" name="target_player_name" placeholder="默认：当前管理员绑定玩家">
                        </label>
                        <label class="block md:col-span-2">
                            <span class="text-sm">玩家 UUID</span>
                            <input type="text" name="target_player_uuid" placeholder="可选：可留空自动解析">
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
                    <button type="submit" class="ta-btn ta-btn-primary w-full">发送测试奖励</button>
                </form>
            </div>
        </aside>
    </div>
</div>

<script>
window.signinRewardsBootstrap = <?= json_encode($bootstrap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

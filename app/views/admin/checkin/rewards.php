<?php
$checkinRewardRules = isset($checkinRewardRules) && is_array($checkinRewardRules) ? $checkinRewardRules : [];
$dailyRules = isset($checkinRewardRules['daily']) && is_array($checkinRewardRules['daily'])
    ? $checkinRewardRules['daily']
    : [];
$monthlyRules = isset($checkinRewardRules['monthly']) && is_array($checkinRewardRules['monthly'])
    ? $checkinRewardRules['monthly']
    : [];
$monthKey = (string)($checkinRewardRules['month_key'] ?? date('Y-m'));
$csrfToken = htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
$saved = isset($_GET['saved']) && (string)$_GET['saved'] === '1';
$hasError = isset($_GET['err']) && (string)$_GET['err'] === 'checkin_rule';

$escape = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$jsonText = static function ($value): string {
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return htmlspecialchars(is_string($json) ? $json : '[]', ENT_QUOTES, 'UTF-8');
};

$renderRuleCard = static function (array $rule, string $scope, string $csrfToken, callable $escape, callable $jsonText): void {
    $id = (int)($rule['id'] ?? 0);
    $day = (int)($rule['day'] ?? ($scope === 'daily' ? 1 : 0));
    $coins = (int)($rule['coins'] ?? 0);
    $points = (int)($rule['points'] ?? 0);
    $enabled = !empty($rule['enabled']);
    $items = isset($rule['items']) && is_array($rule['items']) ? $rule['items'] : [];
    $commands = isset($rule['commands']) && is_array($rule['commands']) ? $rule['commands'] : [];
    ?>
    <form method="post" action="/admin/checkin/rewards/save" class="ta-card space-y-4">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="id" value="<?= $escape($id) ?>">
        <input type="hidden" name="scope" value="<?= $escape($scope) ?>">

        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h4 class="text-base font-semibold">
                    <?= $scope === 'daily' ? '基础日常奖励' : ('月度奖励 - 第 ' . $escape($day) . ' 天') ?>
                </h4>
                <p class="ta-help-text mt-1">
                    <?= $scope === 'daily' ? '每日签到都会叠加这条规则。' : '当本月签到次数达到该天数时额外发放。' ?>
                </p>
            </div>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                <span>启用</span>
            </label>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <label class="block">
                <span class="text-sm font-medium">Day</span>
                <input
                    type="number"
                    name="day"
                    min="1"
                    max="31"
                    value="<?= $escape($day) ?>"
                    <?= $scope === 'daily' ? 'readonly' : '' ?>
                >
            </label>
            <label class="block">
                <span class="text-sm font-medium">Coins</span>
                <input type="number" name="coins" min="0" step="1" value="<?= $escape($coins) ?>">
            </label>
            <label class="block">
                <span class="text-sm font-medium">Points</span>
                <input type="number" name="points" min="0" step="1" value="<?= $escape($points) ?>">
            </label>
            <div class="flex items-end">
                <button type="submit" class="ta-btn ta-btn-primary w-full transition-all hover:scale-[1.02]">
                    保存规则
                </button>
            </div>
        </div>

        <label class="block">
            <span class="text-sm font-medium">items_json</span>
            <textarea name="items_json" rows="5"><?= $jsonText($items) ?></textarea>
        </label>

        <label class="block">
            <span class="text-sm font-medium">commands_json</span>
            <textarea name="commands_json" rows="5"><?= $jsonText($commands) ?></textarea>
        </label>
    </form>
    <?php
};
?>

<div id="checkin-rewards-page" class="ta-checkin-page space-y-6">
    <div class="ta-card">
        <h2 class="text-lg font-semibold">奖励规则</h2>
        <p class="ta-help-text mt-2">当前月份：<?= $escape($monthKey) ?>。第一阶段支持 `daily` 与 `monthly` 两种 scope。</p>

        <?php if ($saved): ?>
            <div class="mt-4 rounded-xl border border-emerald-300/35 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                奖励规则已保存到数据库。
            </div>
        <?php endif; ?>

        <?php if ($hasError): ?>
            <div class="mt-4 rounded-xl border border-rose-300/35 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                奖励规则保存失败，请检查 day、JSON 格式以及命令占位符是否有效。
            </div>
        <?php endif; ?>

        <div class="mt-4 rounded-xl border border-sky-300/25 bg-sky-500/10 px-4 py-3 text-sm text-sky-100">
            `commands_json` 只允许使用 `{player}` 和 `{uuid}` 占位符；`items_json` 必须是 JSON 数组。
        </div>
    </div>

    <section class="space-y-4">
        <div class="ta-card">
            <h3 class="text-base font-semibold">Daily 规则</h3>
            <p class="ta-help-text mt-2">如果还没有 daily 规则，下面会显示一个可直接保存的空白表单。</p>
        </div>

        <?php if ($dailyRules === []): ?>
            <?php $renderRuleCard(['day' => 1, 'coins' => 0, 'points' => 0, 'items' => [], 'commands' => [], 'enabled' => true], 'daily', $csrfToken, $escape, $jsonText); ?>
        <?php else: ?>
            <?php foreach ($dailyRules as $rule): ?>
                <?php $renderRuleCard($rule, 'daily', $csrfToken, $escape, $jsonText); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="space-y-4">
        <div class="ta-card">
            <h3 class="text-base font-semibold">Monthly 规则</h3>
            <p class="ta-help-text mt-2">当玩家本月签到次数达到对应 Day 时，会叠加发放该奖励。</p>
        </div>

        <?php foreach ($monthlyRules as $rule): ?>
            <?php $renderRuleCard($rule, 'monthly', $csrfToken, $escape, $jsonText); ?>
        <?php endforeach; ?>

        <?php $renderRuleCard(['day' => 7, 'coins' => 0, 'points' => 0, 'items' => [], 'commands' => [], 'enabled' => true], 'monthly', $csrfToken, $escape, $jsonText); ?>
    </section>
</div>

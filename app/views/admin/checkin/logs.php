<?php
$checkinLogs = isset($checkinLogs) && is_array($checkinLogs) ? $checkinLogs : [];

$escape = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$renderStatusClass = static function (string $status): string {
    return $status === 'delivered'
        ? 'ta-checkin-status-pill ta-checkin-status-pill--done'
        : 'ta-checkin-status-pill ta-checkin-status-pill--pending';
};
?>

<div id="checkin-logs-page" class="ta-checkin-page space-y-6" style="overflow: visible; max-height: none;">
    <div class="ta-card">
        <h2 class="text-lg font-semibold">签到日志</h2>
        <p class="ta-help-text mt-2">显示最近 100 条真实签到记录与当前发放状态。</p>
    </div>

    <div class="ta-card">
        <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <h3 class="text-base font-semibold">最近记录</h3>
            <span class="ta-help-text">来源：`checkin_records`</span>
        </div>

        <div class="ta-table-wrap">
            <table class="ta-table ta-table-wide">
                <thead>
                <tr>
                    <th>玩家名</th>
                    <th>签到日期</th>
                    <th>连续签到</th>
                    <th>本月签到</th>
                    <th>累计签到</th>
                    <th>发放状态</th>
                    <th>创建时间</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($checkinLogs === []): ?>
                    <tr>
                        <td colspan="7" class="py-10 text-center text-sm text-slate-300">
                            暂无真实签到记录。
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($checkinLogs as $row): ?>
                        <?php
                        $status = (string)($row['delivery_status'] ?? '');
                        ?>
                        <tr>
                            <td><?= $escape($row['username'] ?? '--') ?></td>
                            <td><?= $escape($row['checkin_date'] ?? '--') ?></td>
                            <td><?= $escape($row['streak_days'] ?? 0) ?></td>
                            <td><?= $escape($row['month_days'] ?? 0) ?></td>
                            <td><?= $escape($row['total_days'] ?? 0) ?></td>
                            <td>
                                <span class="<?= $escape($renderStatusClass($status)) ?>">
                                    <?= $escape($status !== '' ? $status : 'pending') ?>
                                </span>
                            </td>
                            <td><?= $escape($row['created_at'] ?? '--') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$checkinStats = isset($checkinStats) && is_array($checkinStats) ? $checkinStats : [];
$topStreaks = isset($checkinStats['top_streaks']) && is_array($checkinStats['top_streaks'])
    ? $checkinStats['top_streaks']
    : [];

$escape = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
?>

<div id="checkin-stats-page" class="ta-checkin-page space-y-6">
    <div class="ta-card">
        <h2 class="text-lg font-semibold">签到统计</h2>
        <p class="ta-help-text mt-2">显示网站端当前签到闭环的真实聚合数据。</p>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div class="ta-card ta-checkin-stat-card transition-all hover:scale-[1.02]">
            <p class="ta-help-text">今日签到人数</p>
            <p class="ta-checkin-stat-value"><?= $escape($checkinStats['today_count'] ?? 0) ?></p>
        </div>
        <div class="ta-card ta-checkin-stat-card transition-all hover:scale-[1.02]">
            <p class="ta-help-text">昨日签到人数</p>
            <p class="ta-checkin-stat-value"><?= $escape($checkinStats['yesterday_count'] ?? 0) ?></p>
        </div>
        <div class="ta-card ta-checkin-stat-card transition-all hover:scale-[1.02]">
            <p class="ta-help-text">本月签到总次数</p>
            <p class="ta-checkin-stat-value"><?= $escape($checkinStats['month_total'] ?? 0) ?></p>
        </div>
        <div class="ta-card ta-checkin-stat-card transition-all hover:scale-[1.02]">
            <p class="ta-help-text">Pending 奖励数</p>
            <p class="ta-checkin-stat-value"><?= $escape($checkinStats['pending_deliveries'] ?? 0) ?></p>
        </div>
        <div class="ta-card ta-checkin-stat-card transition-all hover:scale-[1.02]">
            <p class="ta-help-text">Failed 奖励数</p>
            <p class="ta-checkin-stat-value"><?= $escape($checkinStats['failed_deliveries'] ?? 0) ?></p>
        </div>
        <div class="ta-card ta-checkin-stat-card transition-all hover:scale-[1.02]">
            <p class="ta-help-text">Delivered 奖励数</p>
            <p class="ta-checkin-stat-value"><?= $escape($checkinStats['delivered_deliveries'] ?? 0) ?></p>
        </div>
    </div>

    <div class="ta-card">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-base font-semibold">连续签到 Top</h3>
            <span class="ta-help-text">按每位玩家最近一次签到记录排序</span>
        </div>

        <div class="ta-table-wrap">
            <table class="ta-table ta-table-wide">
                <thead>
                <tr>
                    <th>玩家名</th>
                    <th>连续签到</th>
                    <th>最近签到日期</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($topStreaks === []): ?>
                    <tr>
                        <td colspan="3" class="py-10 text-center text-sm text-slate-300">
                            暂无签到排行数据。
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($topStreaks as $row): ?>
                        <tr>
                            <td><?= $escape($row['username'] ?? '--') ?></td>
                            <td><?= $escape($row['streak_days'] ?? 0) ?> 天</td>
                            <td><?= $escape($row['checkin_date'] ?? '--') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

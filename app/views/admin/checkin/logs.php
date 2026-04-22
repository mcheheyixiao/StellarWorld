<?php
$checkinLogSamples = [
    ['player' => 'Alex_01', 'date' => '2026-04-22 08:03', 'streak' => 6, 'reward' => '金币 130 + 物品 2', 'status' => '已发放'],
    ['player' => 'BuilderFox', 'date' => '2026-04-22 08:15', 'streak' => 14, 'reward' => '金币 170 + 命令 1', 'status' => '待发放'],
    ['player' => 'RedStonePro', 'date' => '2026-04-21 23:58', 'streak' => 31, 'reward' => '金币 255 + 物品 3', 'status' => '已发放'],
    ['player' => 'SkyMiner', 'date' => '2026-04-21 21:12', 'streak' => 3, 'reward' => '金币 115 + 物品 1', 'status' => '已发放'],
    ['player' => 'CrafterN', 'date' => '2026-04-21 20:34', 'streak' => 1, 'reward' => '金币 105', 'status' => '待发放'],
];
?>

<div id="checkin-logs-page" class="ta-checkin-page space-y-6" style="overflow: visible; max-height: none;">
    <div class="ta-card">
        <h2 class="text-lg font-semibold">签到记录</h2>
        <p class="ta-help-text mt-2">筛选和检索玩家签到发奖记录（UI 演示）。</p>

        <div class="mt-4 grid grid-cols-2 gap-4">
            <label class="block">
                <span class="text-sm font-medium">玩家ID</span>
                <input type="text" class="h-10" placeholder="输入玩家ID / 用户名">
            </label>

            <label class="block">
                <span class="text-sm font-medium">开始日期</span>
                <input type="date" class="h-10">
            </label>

            <label class="block">
                <span class="text-sm font-medium">结束日期</span>
                <input type="date" class="h-10">
            </label>

            <label class="block">
                <span class="text-sm font-medium">是否已发放</span>
                <select class="h-10">
                    <option value="">全部</option>
                    <option value="1">已发放</option>
                    <option value="0">待发放</option>
                </select>
            </label>
        </div>

        <div class="flex gap-2 mt-2">
            <button type="button" class="ta-btn ta-btn-primary transition-all hover:scale-[1.02]">搜索</button>
            <button type="button" class="ta-btn ta-btn-secondary transition-all hover:scale-[1.02]">重置筛选</button>
        </div>
    </div>

    <div class="ta-card">
        <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <h3 class="text-base font-semibold">签到记录列表</h3>
            <label class="w-full md:w-auto md:min-w-[280px]">
                <input type="search" placeholder="表格内快速搜索（UI）">
            </label>
        </div>

        <div class="ta-table-wrap">
            <table class="ta-table ta-table-wide">
                <thead>
                <tr>
                    <th>玩家</th>
                    <th>日期</th>
                    <th>连续天数</th>
                    <th>奖励</th>
                    <th>状态</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($checkinLogSamples as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['player'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($row['date'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int)$row['streak'] ?></td>
                        <td><?= htmlspecialchars($row['reward'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="ta-checkin-status-pill <?= $row['status'] === '已发放' ? 'ta-checkin-status-pill--done' : 'ta-checkin-status-pill--pending' ?>">
                                <?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
            <button type="button" class="ta-btn ta-btn-secondary transition-all hover:scale-[1.02]">上一页</button>
            <button type="button" class="ta-btn ta-btn-primary transition-all hover:scale-[1.02]">1</button>
            <button type="button" class="ta-btn ta-btn-secondary transition-all hover:scale-[1.02]">2</button>
            <button type="button" class="ta-btn ta-btn-secondary transition-all hover:scale-[1.02]">3</button>
            <button type="button" class="ta-btn ta-btn-secondary transition-all hover:scale-[1.02]">下一页</button>
        </div>
    </div>
</div>

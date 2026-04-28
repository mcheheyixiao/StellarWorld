<?php
$dailySignTrend = [42, 58, 47, 73, 64, 79, 82];
$dailySignBars = [36, 49, 52, 67, 61, 74, 70];
?>

<div id="checkin-stats-page" class="ta-checkin-page space-y-6">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="ta-card ta-checkin-stat-card transition-all hover:scale-[1.02]">
            <p class="ta-help-text">今日签到人数</p>
            <p class="ta-checkin-stat-value">128</p>
        </div>
        <div class="ta-card ta-checkin-stat-card transition-all hover:scale-[1.02]">
            <p class="ta-help-text">本月签到次数</p>
            <p class="ta-checkin-stat-value">3,842</p>
        </div>
        <div class="ta-card ta-checkin-stat-card transition-all hover:scale-[1.02]">
            <p class="ta-help-text">平均连续天数</p>
            <p class="ta-checkin-stat-value">9.7</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="ta-card">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-semibold">签到趋势（折线图占位）</h3>
                <span class="ta-help-text">最近7天</span>
            </div>
            <div class="ta-checkin-chart-surface">
                <div class="ta-checkin-line-chart">
                    <?php foreach ($dailySignTrend as $value): ?>
                        <div class="ta-checkin-line-dot" style="bottom: <?= (int)$value ?>%"></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="ta-card">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-semibold">每日人数（柱状图占位）</h3>
                <span class="ta-help-text">最近7天</span>
            </div>
            <div class="ta-checkin-chart-surface ta-checkin-bar-chart">
                <?php foreach ($dailySignBars as $index => $value): ?>
                    <div class="ta-checkin-bar-col">
                        <span class="ta-checkin-bar" style="height: <?= (int)$value ?>%"></span>
                        <span class="ta-checkin-bar-label">D<?= (int)($index + 1) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="ta-card">
        <h3 class="text-base font-semibold">玩家详情查询</h3>
        <div class="mt-3 space-y-2">
            <label class="block text-sm font-medium" for="checkin-player-query">玩家ID / 用户名</label>
            <div class="flex gap-2">
                <div class="flex-[5]">
                    <input
                        id="checkin-player-query"
                        type="text"
                        class="h-10"
                        style="margin-top: 0;"
                        placeholder="例如: Alex_01"
                        data-checkin-player-query
                    >
                </div>
                <button
                    type="button"
                    class="ta-btn ta-btn-primary transition-all hover:scale-[1.02] flex-[1] h-10"
                    data-checkin-player-search
                >
                    查询
                </button>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4" data-checkin-player-result>
            <div class="ta-checkin-player-card">
                <p class="ta-help-text">总签到天数</p>
                <p class="ta-checkin-player-value" data-checkin-field-total>186</p>
            </div>
            <div class="ta-checkin-player-card">
                <p class="ta-help-text">当前连续</p>
                <p class="ta-checkin-player-value" data-checkin-field-current>14</p>
            </div>
            <div class="ta-checkin-player-card">
                <p class="ta-help-text">最大连续</p>
                <p class="ta-checkin-player-value" data-checkin-field-max>39</p>
            </div>
            <div class="ta-checkin-player-card">
                <p class="ta-help-text">最近签到时间</p>
                <p class="ta-checkin-player-value ta-checkin-player-value--time" data-checkin-field-last>2026-04-22 08:15:27</p>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var page = document.getElementById('checkin-stats-page');
    if (!page) return;

    var queryInput = page.querySelector('[data-checkin-player-query]');
    var searchBtn = page.querySelector('[data-checkin-player-search]');
    var fieldTotal = page.querySelector('[data-checkin-field-total]');
    var fieldCurrent = page.querySelector('[data-checkin-field-current]');
    var fieldMax = page.querySelector('[data-checkin-field-max]');
    var fieldLast = page.querySelector('[data-checkin-field-last]');

    searchBtn.addEventListener('click', function () {
        var seed = String(queryInput.value || '').trim().length || 7;
        var total = 120 + seed * 3;
        var current = 3 + (seed % 15);
        var max = current + 10 + (seed % 11);

        fieldTotal.textContent = String(total);
        fieldCurrent.textContent = String(current);
        fieldMax.textContent = String(max);
        fieldLast.textContent = '2026-04-22 0' + ((seed % 9) + 1) + ':1' + (seed % 6) + ':3' + (seed % 10);
    });
})();
</script>

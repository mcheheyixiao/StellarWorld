<div id="checkin-stats-page" class="ta-checkin-page space-y-6">
    <div class="ta-card border border-amber-300/35 bg-amber-500/10">
        <h2 class="text-lg font-semibold">统计分析</h2>
        <p class="ta-help-text mt-2">当前页面尚未接入真实的签到统计聚合数据，已移除演示数值与伪查询逻辑。</p>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="ta-card ta-checkin-stat-card transition-all hover:scale-[1.02]">
            <p class="ta-help-text">今日签到人数</p>
            <p class="ta-checkin-stat-value">--</p>
        </div>
        <div class="ta-card ta-checkin-stat-card transition-all hover:scale-[1.02]">
            <p class="ta-help-text">本月签到次数</p>
            <p class="ta-checkin-stat-value">--</p>
        </div>
        <div class="ta-card ta-checkin-stat-card transition-all hover:scale-[1.02]">
            <p class="ta-help-text">平均连续天数</p>
            <p class="ta-checkin-stat-value">--</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="ta-card">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-semibold">签到趋势</h3>
                <span class="ta-help-text">最近7天</span>
            </div>
            <div class="ta-checkin-chart-surface flex items-center justify-center text-sm text-slate-300">
                暂无真实趋势数据
            </div>
        </div>

        <div class="ta-card">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-semibold">每日人数</h3>
                <span class="ta-help-text">最近7天</span>
            </div>
            <div class="ta-checkin-chart-surface flex items-center justify-center text-sm text-slate-300">
                暂无真实每日人数统计
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
                        placeholder="待接入真实查询后启用"
                        disabled
                    >
                </div>
                <button
                    type="button"
                    class="ta-btn ta-btn-primary transition-all hover:scale-[1.02] flex-[1] h-10 opacity-60 cursor-not-allowed"
                    disabled
                >
                    查询
                </button>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
            <div class="ta-checkin-player-card">
                <p class="ta-help-text">总签到天数</p>
                <p class="ta-checkin-player-value">--</p>
            </div>
            <div class="ta-checkin-player-card">
                <p class="ta-help-text">当前连续</p>
                <p class="ta-checkin-player-value">--</p>
            </div>
            <div class="ta-checkin-player-card">
                <p class="ta-help-text">最大连续</p>
                <p class="ta-checkin-player-value">--</p>
            </div>
            <div class="ta-checkin-player-card">
                <p class="ta-help-text">最近签到时间</p>
                <p class="ta-checkin-player-value ta-checkin-player-value--time">--</p>
            </div>
        </div>
    </div>
</div>

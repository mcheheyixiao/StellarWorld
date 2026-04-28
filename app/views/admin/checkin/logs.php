<div id="checkin-logs-page" class="ta-checkin-page space-y-6" style="overflow: visible; max-height: none;">
    <div class="ta-card">
        <h2 class="text-lg font-semibold">签到记录</h2>
        <p class="ta-help-text mt-2">当前页面尚未接入真实的签到发奖日志数据源，已移除演示样本，避免误导为生产数据。</p>

        <div class="mt-4 rounded-xl border border-amber-300/35 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
            暂无真实签到日志可展示。待后端提供签到发奖日志表或统一查询接口后，此页面再接入真实记录。
        </div>

        <div class="mt-4 grid grid-cols-2 gap-4">
            <label class="block">
                <span class="text-sm font-medium">玩家ID</span>
                <input type="text" class="h-10" placeholder="待接入真实查询后启用" disabled>
            </label>

            <label class="block">
                <span class="text-sm font-medium">开始日期</span>
                <input type="date" class="h-10" disabled>
            </label>

            <label class="block">
                <span class="text-sm font-medium">结束日期</span>
                <input type="date" class="h-10" disabled>
            </label>

            <label class="block">
                <span class="text-sm font-medium">是否已发放</span>
                <select class="h-10" disabled>
                    <option value="">全部</option>
                    <option value="1">已发放</option>
                    <option value="0">待发放</option>
                </select>
            </label>
        </div>

        <div class="flex gap-2 mt-2">
            <button type="button" class="ta-btn ta-btn-primary transition-all hover:scale-[1.02] opacity-60 cursor-not-allowed" disabled>搜索</button>
            <button type="button" class="ta-btn ta-btn-secondary transition-all hover:scale-[1.02] opacity-60 cursor-not-allowed" disabled>重置筛选</button>
        </div>
    </div>

    <div class="ta-card">
        <div class="mb-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <h3 class="text-base font-semibold">签到记录列表</h3>
            <label class="w-full md:w-auto md:min-w-[280px]">
                <input type="search" placeholder="待接入真实数据后启用" disabled>
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
                    <tr>
                        <td colspan="5" class="py-10 text-center text-sm text-slate-300">
                            暂无真实签到日志数据，当前页面仅保留后台入口与布局容器。
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
            <button type="button" class="ta-btn ta-btn-secondary transition-all hover:scale-[1.02] opacity-60 cursor-not-allowed" disabled>上一页</button>
            <button type="button" class="ta-btn ta-btn-primary transition-all hover:scale-[1.02] opacity-60 cursor-not-allowed" disabled>1</button>
            <button type="button" class="ta-btn ta-btn-secondary transition-all hover:scale-[1.02] opacity-60 cursor-not-allowed" disabled>下一页</button>
        </div>
    </div>
</div>

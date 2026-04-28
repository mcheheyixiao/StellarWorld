<div id="checkin-rewards-page" class="ta-checkin-page space-y-6">
    <div class="ta-card">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-2">
                <button type="button" class="ta-btn ta-btn-secondary transition-all hover:scale-[1.02]" data-checkin-month-prev>上个月</button>
                <h2 class="text-lg font-semibold" data-checkin-month-label>2026年04月</h2>
                <button type="button" class="ta-btn ta-btn-secondary transition-all hover:scale-[1.02]" data-checkin-month-next>下个月</button>
            </div>
            <button type="button" class="ta-btn ta-btn-primary transition-all hover:scale-[1.02]" data-checkin-reset-month>重置本月</button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.6fr_1fr]">
        <section class="ta-card">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="text-base font-semibold">每日奖励日历编辑器</h3>
                <span class="ta-help-text">点击日期可编辑奖励</span>
            </div>

            <div class="ta-checkin-weekdays">
                <span>一</span>
                <span>二</span>
                <span>三</span>
                <span>四</span>
                <span>五</span>
                <span>六</span>
                <span>日</span>
            </div>

            <div class="ta-checkin-calendar-grid" data-checkin-calendar-grid></div>
        </section>

        <aside class="ta-card ta-checkin-preview-card">
            <h3 class="text-base font-semibold">当前日期奖励预览</h3>
            <div class="mt-3 space-y-2" data-checkin-preview-meta></div>

            <h4 class="mt-5 text-sm font-semibold">奖励 JSON 预览（只读）</h4>
            <pre class="ta-checkin-json-preview mt-2" data-checkin-json-preview></pre>

            <button type="button" class="ta-btn ta-btn-secondary mt-4 w-full transition-all hover:scale-[1.02]" data-checkin-open-editor>
                编辑当前日期奖励
            </button>
        </aside>
    </div>

    <div class="ta-checkin-modal hidden" data-checkin-modal aria-hidden="true">
        <div class="ta-checkin-modal-backdrop" data-checkin-modal-close></div>
        <div class="ta-checkin-modal-panel ta-card">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold">奖励编辑</h3>
                    <p class="ta-help-text" data-checkin-modal-title>编辑 1 号奖励</p>
                </div>
                <button type="button" class="ta-btn ta-btn-secondary transition-all hover:scale-[1.02]" data-checkin-modal-close>关闭</button>
            </div>

            <div class="mt-4 space-y-4">
                <label class="block">
                    <span class="text-sm font-medium">金币：</span>
                    <input type="number" min="0" step="1" value="120" data-checkin-input-coins>
                </label>

                <label class="block">
                    <span class="text-sm font-medium">连续加成：</span>
                    <input type="number" min="0" step="1" value="10" data-checkin-input-streak>
                </label>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-semibold">物品：</h4>
                        <button type="button" class="ta-btn ta-btn-secondary transition-all hover:scale-[1.02]" data-checkin-add-item>+ 添加物品</button>
                    </div>
                    <div class="space-y-2" data-checkin-item-list></div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-semibold">命令：</h4>
                        <button type="button" class="ta-btn ta-btn-secondary transition-all hover:scale-[1.02]" data-checkin-add-command>+ 添加命令</button>
                    </div>
                    <div class="space-y-2" data-checkin-command-list></div>
                </div>

                <div class="space-y-2">
                    <h4 class="text-sm font-semibold">作用范围：</h4>
                    <label class="ta-checkin-scope-option">
                        <input type="radio" name="checkin_scope" value="month" checked data-checkin-scope>
                        <span>仅本月</span>
                    </label>
                    <label class="ta-checkin-scope-option">
                        <input type="radio" name="checkin_scope" value="global" data-checkin-scope>
                        <span>全局模板</span>
                    </label>
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="ta-btn ta-btn-secondary transition-all hover:scale-[1.02]" data-checkin-modal-close>取消</button>
                <button type="button" class="ta-btn ta-btn-primary transition-all hover:scale-[1.02]" data-checkin-save>保存奖励</button>
            </div>
        </div>
    </div>

    <template data-checkin-item-template>
        <div class="ta-checkin-input-card">
            <input type="text" placeholder="物品 ID / 名称" data-checkin-item-name aria-label="Reward item name">
            <input type="number" min="1" step="1" value="1" data-checkin-item-amount aria-label="Reward item amount">
            <button type="button" class="ta-btn ta-btn-secondary transition-all hover:scale-[1.02]" data-checkin-remove-row>移除</button>
        </div>
    </template>

    <template data-checkin-command-template>
        <div class="ta-checkin-input-card">
            <input type="text" placeholder="输入命令（例如：give @p diamond 1）" data-checkin-command-text aria-label="Reward command">
            <button type="button" class="ta-btn ta-btn-secondary transition-all hover:scale-[1.02]" data-checkin-remove-row>移除</button>
        </div>
    </template>
</div>

<script src="/scripts/admin-checkin-rewards.js"></script>

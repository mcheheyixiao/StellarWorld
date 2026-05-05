<?php
$redeemCsrfToken = htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
?>

<div id="redeem-admin-root" class="space-y-6" data-csrf-token="<?= $redeemCsrfToken ?>">
    <div class="ta-card">
        <h1>卡密系统</h1>
        <p class="ta-help-text">V1 功能：分类管理、批量生成、卡密列表、吊销/删除、兑换日志、发布统计。</p>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5" id="redeem-stats-grid">
        <div class="ta-card">
            <p class="ta-help-text">总卡密数</p>
            <p id="redeem-stat-total" class="text-2xl font-bold">-</p>
        </div>
        <div class="ta-card">
            <p class="ta-help-text">可发布卡密数</p>
            <p id="redeem-stat-publishable" class="text-2xl font-bold">-</p>
        </div>
        <div class="ta-card">
            <p class="ta-help-text">已兑换次数</p>
            <p id="redeem-stat-used" class="text-2xl font-bold">-</p>
        </div>
        <div class="ta-card">
            <p class="ta-help-text">失败兑换次数</p>
            <p id="redeem-stat-failed" class="text-2xl font-bold">-</p>
        </div>
        <div class="ta-card">
            <p class="ta-help-text">已吊销数量</p>
            <p id="redeem-stat-revoked" class="text-2xl font-bold">-</p>
        </div>
    </div>

    <div class="ta-card space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-lg font-semibold">卡密列表</h2>
            <button type="button" class="ta-btn ta-btn-secondary" id="redeem-keys-refresh-btn">刷新</button>
            <button type="button" class="ta-btn ta-btn-secondary" id="redeem-keys-revoke-batch-btn">批量吊销</button>
            <button type="button" class="ta-btn ta-btn-secondary" id="redeem-keys-delete-batch-btn">批量删除</button>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <label>
                <span class="ta-help-text">状态</span>
                <select id="redeem-keys-filter-status">
                    <option value="">全部</option>
                    <option value="available">available</option>
                    <option value="revoked">revoked</option>
                    <option value="deleted">deleted</option>
                </select>
            </label>
            <label>
                <span class="ta-help-text">分类</span>
                <select id="redeem-keys-filter-category">
                    <option value="">全部</option>
                </select>
            </label>
            <label>
                <span class="ta-help-text">关键字</span>
                <input type="text" id="redeem-keys-filter-q" placeholder="按卡密/备注筛选">
            </label>
            <label>
                <span class="ta-help-text">每页</span>
                <select id="redeem-keys-filter-per-page">
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </label>
        </div>

        <div class="ta-table-wrap">
            <table class="ta-table" id="redeem-keys-table">
                <thead>
                <tr>
                    <th><input type="checkbox" id="redeem-keys-select-all"></th>
                    <th>ID</th>
                    <th>卡密</th>
                    <th>分类</th>
                    <th>状态</th>
                    <th>次数</th>
                    <th>过期时间</th>
                    <th>备注</th>
                    <th>操作</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="flex items-center gap-2 justify-end" id="redeem-keys-pagination">
            <button type="button" class="ta-btn ta-btn-secondary" id="redeem-keys-prev">Prev</button>
            <span class="ta-help-text" id="redeem-keys-page-text">Page 1 / 1</span>
            <button type="button" class="ta-btn ta-btn-secondary" id="redeem-keys-next">Next</button>
        </div>
    </div>

    <div class="ta-card space-y-4">
        <h2 class="text-lg font-semibold">批量生成</h2>
        <form id="redeem-batch-form" class="space-y-3">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <label>
                    <span class="ta-help-text">分类</span>
                    <select name="categoryId" id="redeem-batch-category">
                        <option value="">不选择分类</option>
                    </select>
                </label>
                <label>
                    <span class="ta-help-text">生成数量（最大500）</span>
                    <input type="number" name="count" min="1" max="500" value="10" required>
                </label>
                <label>
                    <span class="ta-help-text">卡密长度</span>
                    <input type="number" name="length" min="8" max="64" value="16" required>
                </label>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <label>
                    <span class="ta-help-text">最大使用次数</span>
                    <input type="number" name="maxUses" min="1" value="1" required>
                </label>
                <label>
                    <span class="ta-help-text">过期时间（可空）</span>
                    <input type="datetime-local" name="expiresAt">
                </label>
                <label>
                    <span class="ta-help-text">备注</span>
                    <input type="text" name="remark" maxlength="255">
                </label>
            </div>

            <label>
                <span class="ta-help-text">命令模板（可空：若选择分类则继承分类模板）</span>
                <textarea name="commandTemplate" rows="5" placeholder="每行一条命令，例如：eco give {player} 1000"></textarea>
            </label>

            <div class="flex items-center gap-2">
                <button type="submit" class="ta-btn ta-btn-primary">生成并下载 CSV</button>
                <span class="ta-help-text" id="redeem-batch-result"></span>
            </div>
        </form>
    </div>

    <div class="ta-card space-y-4">
        <h2 class="text-lg font-semibold">分类管理</h2>
        <form id="redeem-category-create-form" class="space-y-3">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <label>
                    <span class="ta-help-text">分类名称</span>
                    <input type="text" name="name" maxlength="128" required>
                </label>
                <label>
                    <span class="ta-help-text">状态</span>
                    <select name="status">
                        <option value="enabled">enabled</option>
                        <option value="disabled">disabled</option>
                    </select>
                </label>
            </div>
            <label>
                <span class="ta-help-text">描述</span>
                <input type="text" name="description">
            </label>
            <label>
                <span class="ta-help-text">默认命令模板</span>
                <textarea name="defaultCommandTemplate" rows="4" required></textarea>
            </label>
            <button type="submit" class="ta-btn ta-btn-primary">新增分类</button>
        </form>

        <div class="ta-table-wrap">
            <table class="ta-table" id="redeem-categories-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>名称</th>
                    <th>状态</th>
                    <th>描述</th>
                    <th>默认模板</th>
                    <th>操作</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="ta-card space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-lg font-semibold">兑换日志</h2>
            <button type="button" class="ta-btn ta-btn-secondary" id="redeem-logs-refresh-btn">刷新</button>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <label>
                <span class="ta-help-text">状态</span>
                <select id="redeem-logs-filter-status">
                    <option value="">全部</option>
                    <option value="executing">executing</option>
                    <option value="success">success</option>
                    <option value="failed">failed</option>
                </select>
            </label>
            <label>
                <span class="ta-help-text">分类</span>
                <select id="redeem-logs-filter-category">
                    <option value="">全部</option>
                </select>
            </label>
            <label>
                <span class="ta-help-text">关键字</span>
                <input type="text" id="redeem-logs-filter-q" placeholder="玩家/UUID/服务器ID">
            </label>
            <label>
                <span class="ta-help-text">每页</span>
                <select id="redeem-logs-filter-per-page">
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </label>
        </div>

        <div class="ta-table-wrap">
            <table class="ta-table" id="redeem-logs-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>时间</th>
                    <th>玩家</th>
                    <th>UUID</th>
                    <th>服务器</th>
                    <th>世界</th>
                    <th>状态</th>
                    <th>失败原因</th>
                    <th>命令快照</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="flex items-center gap-2 justify-end" id="redeem-logs-pagination">
            <button type="button" class="ta-btn ta-btn-secondary" id="redeem-logs-prev">Prev</button>
            <span class="ta-help-text" id="redeem-logs-page-text">Page 1 / 1</span>
            <button type="button" class="ta-btn ta-btn-secondary" id="redeem-logs-next">Next</button>
        </div>
    </div>
</div>

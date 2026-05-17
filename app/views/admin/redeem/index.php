<?php
$redeemCsrfToken = htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
?>

<style>
/* Redeem UI v4 */
.redeem-page-v4 {
  --redeem-bg: #f5f7fb;
  --redeem-card: #ffffff;
  --redeem-card-soft: #f8fafc;
  --redeem-border: #e5e7eb;
  --redeem-border-strong: #cbd5e1;
  --redeem-text: #0f172a;
  --redeem-muted: #64748b;
  --redeem-subtle: #94a3b8;
  --redeem-primary: #2563eb;
  --redeem-primary-600: #1d4ed8;
  --redeem-primary-soft: #eff6ff;
  --redeem-green: #16a34a;
  --redeem-green-soft: #ecfdf5;
  --redeem-red: #ef4444;
  --redeem-red-soft: #fef2f2;
  --redeem-orange: #f97316;
  --redeem-orange-soft: #fff7ed;
  --redeem-purple: #7c3aed;
  --redeem-purple-soft: #f5f3ff;
  --redeem-cyan: #0891b2;
  --redeem-cyan-soft: #ecfeff;
  --redeem-radius-lg: 18px;
  --redeem-radius-md: 12px;
  --redeem-shadow-card: 0 16px 40px rgba(15, 23, 42, 0.06);
  --redeem-shadow-hover: 0 18px 48px rgba(15, 23, 42, 0.10);
  color: var(--redeem-text);
  background:
    radial-gradient(circle at top left, rgba(37, 99, 235, 0.08), transparent 32rem),
    var(--redeem-bg);
  padding: 4px;
}

.redeem-page-v4.space-y-6 > * + * {
  margin-top: 20px;
}

.redeem-page-header,
.redeem-section-card,
.redeem-config-card,
.redeem-preview-card,
.redeem-stat-card {
  background: var(--redeem-card);
  border: 1px solid var(--redeem-border);
  border-radius: var(--redeem-radius-lg);
  box-shadow: var(--redeem-shadow-card);
}

.redeem-page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 22px 24px;
}

.redeem-eyebrow {
  margin: 0;
  color: var(--redeem-muted);
  font-size: 13px;
  font-weight: 600;
}

.redeem-page-title {
  margin: 4px 0 6px;
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--redeem-text) !important;
}

.redeem-page-desc {
  margin: 0;
  color: var(--redeem-muted) !important;
  font-size: 14px;
}

.redeem-header-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 10px;
}

.redeem-stats-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 16px;
}

.redeem-stat-card {
  position: relative;
  overflow: hidden;
  padding: 18px;
  min-height: 118px;
  transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}

.redeem-stat-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--redeem-shadow-hover);
  border-color: var(--redeem-border-strong);
}

.redeem-stat-icon {
  width: 42px;
  height: 42px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 14px;
  margin-bottom: 12px;
  font-weight: 800;
  font-size: 14px;
}

.redeem-stat-theme-total .redeem-stat-icon {
  color: var(--redeem-purple);
  background: var(--redeem-purple-soft);
}
.redeem-stat-theme-publishable .redeem-stat-icon,
.redeem-stat-theme-success .redeem-stat-icon {
  color: var(--redeem-green);
  background: var(--redeem-green-soft);
}
.redeem-stat-theme-used .redeem-stat-icon {
  color: var(--redeem-primary);
  background: var(--redeem-primary-soft);
}
.redeem-stat-theme-failed .redeem-stat-icon,
.redeem-stat-theme-today-failed .redeem-stat-icon {
  color: var(--redeem-red);
  background: var(--redeem-red-soft);
}
.redeem-stat-theme-revoked .redeem-stat-icon {
  color: var(--redeem-orange);
  background: var(--redeem-orange-soft);
}
.redeem-stat-theme-batch .redeem-stat-icon {
  color: var(--redeem-cyan);
  background: var(--redeem-cyan-soft);
}
.redeem-stat-theme-pending .redeem-stat-icon {
  color: #db2777;
  background: #fdf2f8;
}

.redeem-stat-label {
  color: var(--redeem-muted);
  font-size: 13px;
  font-weight: 600;
}

.redeem-stat-value {
  margin-top: 4px;
  color: var(--redeem-text);
  font-size: 28px;
  line-height: 1.1;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.redeem-stat-foot {
  margin-top: 8px;
  color: var(--redeem-muted);
  font-size: 12px;
}

.redeem-workbench {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 18px;
  align-items: start;
}

.redeem-section-card,
.redeem-config-card,
.redeem-preview-card {
  padding: 20px;
}

.redeem-section-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 16px;
}

.redeem-section-title {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  letter-spacing: -0.01em;
  color: var(--redeem-text) !important;
}

.redeem-section-subtitle {
  margin-top: 4px;
  margin-bottom: 0;
  color: var(--redeem-muted) !important;
  font-size: 13px;
}

.redeem-actions-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.redeem-form-section {
  border: 1px solid var(--redeem-border);
  border-radius: 14px;
  background: var(--redeem-card-soft);
  padding: 14px;
}

.redeem-form-section + .redeem-form-section {
  margin-top: 12px;
}

.redeem-form-section-title {
  margin: 0 0 12px;
  font-size: 14px;
  color: #1e293b;
  font-weight: 800;
}

.redeem-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px 18px;
}

.redeem-field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.redeem-field--full {
  grid-column: 1 / -1;
}

.redeem-field label,
.redeem-field-label {
  color: #334155;
  font-size: 13px;
  font-weight: 700;
}

.redeem-field small,
.redeem-field-help {
  color: var(--redeem-muted);
  font-size: 12px;
}

.redeem-checkbox-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.redeem-check-field {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 40px;
  border: 1px solid var(--redeem-border);
  border-radius: 11px;
  background: #fff;
  padding: 0 10px;
}

.redeem-check-field input[type="checkbox"] {
  width: 15px;
  height: 15px;
}

.redeem-page-v4 input[type="text"],
.redeem-page-v4 input[type="number"],
.redeem-page-v4 input[type="datetime-local"],
.redeem-page-v4 select,
.redeem-page-v4 textarea {
  width: 100%;
  min-height: 40px;
  border: 1px solid var(--redeem-border);
  border-radius: 11px;
  background: #fff;
  color: var(--redeem-text);
  padding: 9px 11px;
  font-size: 14px;
  outline: none;
  transition: border-color .16s ease, box-shadow .16s ease, background-color .16s ease;
}

.redeem-page-v4 textarea {
  min-height: 96px;
  resize: vertical;
}

.redeem-page-v4 input:focus,
.redeem-page-v4 select:focus,
.redeem-page-v4 textarea:focus {
  border-color: rgba(37, 99, 235, 0.65);
  box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.10);
}

.redeem-filter-stack {
  display: grid;
  gap: 10px;
  margin-bottom: 14px;
}

.redeem-filter-bar {
  display: grid;
  grid-template-columns: repeat(5, minmax(150px, 1fr));
  gap: 12px;
  padding: 14px;
  border: 1px solid var(--redeem-border);
  border-radius: 14px;
  background: var(--redeem-card-soft);
}

.redeem-filter-bar.is-advanced {
  border-style: dashed;
}

.redeem-page-v4 .ta-btn,
.redeem-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 38px;
  border-radius: 11px;
  padding: 8px 14px;
  font-size: 13px;
  font-weight: 800;
  transition: transform .16s ease, box-shadow .16s ease, background-color .16s ease, border-color .16s ease;
}

.redeem-page-v4 .ta-btn:hover,
.redeem-btn:hover {
  transform: translateY(-1px);
}

.redeem-page-v4 .ta-btn-primary,
.redeem-btn-primary {
  border: 1px solid var(--redeem-primary);
  background: linear-gradient(180deg, #3b82f6, var(--redeem-primary));
  color: #fff;
  box-shadow: 0 10px 22px rgba(37, 99, 235, 0.22);
}

.redeem-page-v4 .ta-btn-secondary,
.redeem-btn-secondary {
  border: 1px solid var(--redeem-border);
  background: #fff;
  color: #334155;
}

.redeem-page-v4 .ta-btn-secondary:hover,
.redeem-btn-secondary:hover {
  border-color: var(--redeem-border-strong);
  background: #f8fafc;
  color: #1e293b;
}

.redeem-btn-danger {
  border-color: #fecaca !important;
  background: #fef2f2 !important;
  color: #b91c1c !important;
}

.redeem-btn-danger:hover {
  border-color: #fca5a5 !important;
  background: #fee2e2 !important;
}

.redeem-table-shell,
.redeem-page-v4 .ta-table-wrap {
  overflow-x: auto;
  border: 1px solid var(--redeem-border);
  border-radius: 14px;
  background: #fff;
}

.redeem-page-v4 .ta-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  min-width: 980px;
  margin: 0;
  font-size: 13px;
}

.redeem-page-v4 .ta-table th {
  position: sticky;
  top: 0;
  z-index: 1;
  background: #f8fafc;
  color: #475569;
  font-size: 12px;
  font-weight: 800;
  text-transform: none;
  letter-spacing: 0;
  text-align: left;
  white-space: nowrap;
  border-bottom: 1px solid var(--redeem-border);
  padding: 12px 14px;
}

.redeem-page-v4 .ta-table td {
  color: #1e293b;
  vertical-align: middle;
  white-space: nowrap;
  border-bottom: 1px solid #eef2f7;
  padding: 12px 14px;
}

.redeem-page-v4 .ta-table td:last-child,
.redeem-page-v4 .ta-table th:last-child {
  text-align: right;
}

.redeem-page-v4 .ta-table tbody tr:hover {
  background: #f8fafc;
}

.redeem-page-v4 .ta-table tbody tr:last-child td {
  border-bottom: 0;
}

.redeem-page-v4 .ta-action-stack {
  align-items: stretch;
}

.redeem-page-v4 .ta-action-stack .ta-btn,
.redeem-page-v4 .ta-action-stack input,
.redeem-page-v4 .ta-action-stack textarea {
  width: 100%;
}

.redeem-status-pill,
.redeem-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border-radius: 999px;
  padding: 4px 9px;
  font-size: 12px;
  font-weight: 800;
  line-height: 1;
  border: 1px solid transparent;
}

.redeem-status-available,
.redeem-status-success,
.redeem-status-handled,
.redeem-badge-green {
  color: #047857;
  background: #ecfdf5;
  border-color: #bbf7d0;
}

.redeem-status-revoked,
.redeem-status-pending,
.redeem-badge-orange {
  color: #c2410c;
  background: #fff7ed;
  border-color: #fed7aa;
}

.redeem-status-deleted,
.redeem-status-ignored,
.redeem-badge-gray {
  color: #475569;
  background: #f1f5f9;
  border-color: #e2e8f0;
}

.redeem-status-failed,
.redeem-status-error,
.redeem-badge-red {
  color: #b91c1c;
  background: #fef2f2;
  border-color: #fecaca;
}

.redeem-status-executing,
.redeem-badge-blue {
  color: #1d4ed8;
  background: #eff6ff;
  border-color: #bfdbfe;
}

.redeem-pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  padding-top: 12px;
}

.redeem-preview-list {
  display: grid;
  gap: 12px;
}

.redeem-preview-item {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding-bottom: 10px;
  border-bottom: 1px dashed var(--redeem-border);
}

.redeem-preview-item span:first-child {
  color: var(--redeem-muted);
  font-size: 13px;
}

.redeem-preview-item strong {
  color: var(--redeem-text);
  font-size: 13px;
}

.redeem-preview-note {
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px dashed var(--redeem-border-strong);
  background: var(--redeem-card-soft);
  color: var(--redeem-muted);
  font-size: 12px;
}

.redeem-two-grid {
  display: grid;
  gap: 18px;
  grid-template-columns: minmax(0, 1fr);
}

@media (min-width: 1280px) {
  .redeem-two-grid {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  }
}

@media (max-width: 1279px) {
  .redeem-stats-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .redeem-workbench {
    grid-template-columns: 1fr;
  }

  .redeem-filter-bar {
    grid-template-columns: repeat(3, minmax(150px, 1fr));
  }
}

@media (max-width: 767px) {
  .redeem-page-header {
    flex-direction: column;
    padding: 18px;
  }

  .redeem-header-actions {
    justify-content: flex-start;
    width: 100%;
  }

  .redeem-stats-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .redeem-form-grid,
  .redeem-filter-bar,
  .redeem-checkbox-grid {
    grid-template-columns: 1fr;
  }

  .redeem-page-title {
    font-size: 23px;
  }

  .redeem-section-card,
  .redeem-config-card,
  .redeem-preview-card {
    padding: 16px;
  }
}

@media (max-width: 480px) {
  .redeem-stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<div id="redeem-admin-root" class="redeem-page redeem-page-v4 space-y-6" data-csrf-token="<?= $redeemCsrfToken ?>">
    <section class="redeem-page-header">
        <div class="redeem-header-copy">
            <p class="redeem-eyebrow">卡密系统 / 卡密管理</p>
            <h1 class="redeem-page-title">卡密管理</h1>
            <p class="redeem-page-desc">统一管理卡密生成、发放、兑换记录与审计日志。</p>
        </div>
        <div class="redeem-header-actions">
            <button type="button" class="ta-btn ta-btn-secondary redeem-btn redeem-btn-secondary" id="redeem-header-refresh-btn">刷新</button>
            <button type="button" class="ta-btn ta-btn-secondary redeem-btn redeem-btn-secondary" id="redeem-header-export-btn">导出当前筛选 CSV</button>
            <button type="submit" form="redeem-batch-form" class="ta-btn ta-btn-primary redeem-btn redeem-btn-primary">批量生成卡密</button>
        </div>
    </section>

    <div class="redeem-stats-grid" id="redeem-stats-grid">
        <article class="redeem-stat-card redeem-stat-theme-total">
            <div class="redeem-stat-icon">券</div>
            <div class="redeem-stat-label">总卡密</div>
            <div id="redeem-stat-total" class="redeem-stat-value">-</div>
            <div class="redeem-stat-foot">当前系统累计</div>
        </article>
        <article class="redeem-stat-card redeem-stat-theme-publishable">
            <div class="redeem-stat-icon">✓</div>
            <div class="redeem-stat-label">可发布</div>
            <div id="redeem-stat-publishable" class="redeem-stat-value">-</div>
            <div class="redeem-stat-foot">可继续发放</div>
        </article>
        <article class="redeem-stat-card redeem-stat-theme-used">
            <div class="redeem-stat-icon">↗</div>
            <div class="redeem-stat-label">已兑换</div>
            <div id="redeem-stat-used" class="redeem-stat-value">-</div>
            <div class="redeem-stat-foot">累计兑换次数</div>
        </article>
        <article class="redeem-stat-card redeem-stat-theme-failed">
            <div class="redeem-stat-icon">!</div>
            <div class="redeem-stat-label">失败</div>
            <div id="redeem-stat-failed" class="redeem-stat-value">-</div>
            <div class="redeem-stat-foot">累计失败次数</div>
        </article>
        <article class="redeem-stat-card redeem-stat-theme-revoked">
            <div class="redeem-stat-icon">×</div>
            <div class="redeem-stat-label">已吊销</div>
            <div id="redeem-stat-revoked" class="redeem-stat-value">-</div>
            <div class="redeem-stat-foot">不可再使用</div>
        </article>
        <article class="redeem-stat-card redeem-stat-theme-batch">
            <div class="redeem-stat-icon">#</div>
            <div class="redeem-stat-label">批次</div>
            <div id="redeem-stat-batch-count" class="redeem-stat-value">-</div>
            <div class="redeem-stat-foot">已创建批次数</div>
        </article>
        <article class="redeem-stat-card redeem-stat-theme-pending">
            <div class="redeem-stat-icon">…</div>
            <div class="redeem-stat-label">待处理失败</div>
            <div id="redeem-stat-pending-failed" class="redeem-stat-value">-</div>
            <div class="redeem-stat-foot">需要人工复核</div>
        </article>
        <article class="redeem-stat-card redeem-stat-theme-success">
            <div class="redeem-stat-icon">今✓</div>
            <div class="redeem-stat-label">今日成功</div>
            <div id="redeem-stat-today-success" class="redeem-stat-value">-</div>
            <div class="redeem-stat-foot">今日兑换成功</div>
        </article>
        <article class="redeem-stat-card redeem-stat-theme-today-failed">
            <div class="redeem-stat-icon">今!</div>
            <div class="redeem-stat-label">今日失败</div>
            <div id="redeem-stat-today-failed" class="redeem-stat-value">-</div>
            <div class="redeem-stat-foot">今日兑换失败</div>
        </article>
    </div>

    <section class="redeem-workbench">
        <div class="redeem-config-card">
            <div class="redeem-section-head">
                <div>
                    <h2 class="redeem-section-title">批量生成配置</h2>
                    <p class="redeem-section-subtitle">按分区设置卡密参数，提交后保持原有生成与下载流程。</p>
                </div>
            </div>

            <form id="redeem-batch-form" class="space-y-3">
                <div class="redeem-form-section">
                    <h3 class="redeem-form-section-title">基础信息</h3>
                    <div class="redeem-form-grid">
                        <div class="redeem-field">
                            <label for="redeem-batch-category">分类</label>
                            <select name="categoryId" id="redeem-batch-category">
                                <option value="">不选择分类</option>
                            </select>
                        </div>
                        <div class="redeem-field">
                            <label>生成数量（最大 500）</label>
                            <input type="number" name="count" min="1" max="500" value="10" required>
                        </div>
                        <div class="redeem-field">
                            <label>卡密长度</label>
                            <input type="number" name="length" min="8" max="64" value="16" required>
                        </div>
                        <div class="redeem-field">
                            <label>最大使用次数</label>
                            <input type="number" name="maxUses" min="1" value="1" required>
                        </div>
                        <div class="redeem-field">
                            <label>过期时间（可空）</label>
                            <input type="datetime-local" name="expiresAt">
                        </div>
                        <div class="redeem-field">
                            <label>批次名称</label>
                            <input type="text" name="batchName" maxlength="128" placeholder="例如：2026春节活动码">
                        </div>
                        <div class="redeem-field">
                            <label>渠道 / 来源</label>
                            <input type="text" name="channel" maxlength="128" placeholder="例如：event-2026-spring">
                        </div>
                        <div class="redeem-field">
                            <label>备注</label>
                            <input type="text" name="remark" maxlength="255">
                        </div>
                    </div>
                </div>

                <div class="redeem-form-section">
                    <h3 class="redeem-form-section-title">规则限制</h3>
                    <div class="redeem-form-grid">
                        <div class="redeem-field redeem-field--full">
                            <label>允许服务器 ID（每行一个或逗号分隔，留空=不限）</label>
                            <textarea name="allowedServerIds" rows="3" placeholder="survival-1&#10;resource-1"></textarea>
                        </div>
                        <div class="redeem-field">
                            <label>限定玩家 UUID</label>
                            <input type="text" name="boundPlayerUuid" maxlength="64" placeholder="不填则不限">
                        </div>
                        <div class="redeem-field">
                            <label>限定玩家名（辅助）</label>
                            <input type="text" name="boundPlayerName" maxlength="64" placeholder="仅辅助校验/显示">
                        </div>
                        <div class="redeem-field">
                            <label>每个玩家限制</label>
                            <input type="number" name="perPlayerLimit" min="0" value="0">
                        </div>
                        <div class="redeem-field">
                            <label>每个网站账号限制</label>
                            <input type="number" name="perAccountLimit" min="0" value="0">
                        </div>
                        <div class="redeem-field redeem-field--full">
                            <div class="redeem-checkbox-grid">
                                <label class="redeem-check-field">
                                    <input type="checkbox" name="requireBoundAccount" value="1">
                                    <span>要求绑定网站账号</span>
                                </label>
                                <label class="redeem-check-field">
                                    <input type="checkbox" name="requireEmailVerified" value="1">
                                    <span>要求邮箱已验证</span>
                                </label>
                                <label class="redeem-check-field">
                                    <input type="checkbox" name="requireAccountActive" value="1">
                                    <span>要求账号状态正常</span>
                                </label>
                            </div>
                        </div>
                        <div class="redeem-field redeem-field--full">
                            <label>规则备注</label>
                            <input type="text" name="ruleNote" maxlength="255" placeholder="用于运营说明">
                        </div>
                    </div>
                </div>

                <div class="redeem-form-section">
                    <h3 class="redeem-form-section-title">命令模板</h3>
                    <div class="redeem-field">
                        <label>命令模板（可空：若选择分类则继承分类模板）</label>
                        <textarea name="commandTemplate" rows="5" placeholder="每行一条命令，例如：eco give {player} 1000"></textarea>
                    </div>
                </div>

                <div class="redeem-actions-row">
                    <button type="submit" class="ta-btn ta-btn-primary redeem-btn redeem-btn-primary">生成卡密并下载 CSV</button>
                    <span class="ta-help-text" id="redeem-batch-result"></span>
                </div>
            </form>
        </div>

        <aside class="redeem-preview-card redeem-generate-preview">
            <div class="redeem-section-head">
                <div>
                    <h2 class="redeem-section-title">生成预览</h2>
                    <p class="redeem-section-subtitle">提交前摘要（静态展示）</p>
                </div>
            </div>
            <div class="redeem-preview-list">
                <div class="redeem-preview-item">
                    <span>预计生成数量</span>
                    <strong>根据左侧配置生成</strong>
                </div>
                <div class="redeem-preview-item">
                    <span>长度 / 次数</span>
                    <strong>按表单设置</strong>
                </div>
                <div class="redeem-preview-item">
                    <span>有效期</span>
                    <strong>可设置过期时间</strong>
                </div>
                <div class="redeem-preview-item">
                    <span>渠道 / 批次</span>
                    <strong>用于追踪发放来源</strong>
                </div>
                <div class="redeem-preview-item">
                    <span>规则限制</span>
                    <strong>支持服务器与账号约束</strong>
                </div>
            </div>
            <p class="redeem-preview-note">提交后将按原流程自动下载 CSV，不会改变任何接口与业务行为。</p>
        </aside>
    </section>

    <section class="redeem-section-card">
        <div class="redeem-section-head">
            <div>
                <h2 class="redeem-section-title">卡密列表</h2>
                <p class="redeem-section-subtitle">按状态、分类、批次和规则条件筛选，支持批量操作。</p>
            </div>
            <div class="redeem-actions-row">
                <button type="button" class="ta-btn ta-btn-secondary redeem-btn redeem-btn-secondary" id="redeem-keys-refresh-btn">刷新</button>
                <button type="button" class="ta-btn ta-btn-secondary redeem-btn redeem-btn-secondary redeem-btn-danger" id="redeem-keys-revoke-batch-btn">批量吊销</button>
                <button type="button" class="ta-btn ta-btn-secondary redeem-btn redeem-btn-secondary redeem-btn-danger" id="redeem-keys-delete-batch-btn">批量软删除</button>
                <button type="button" class="ta-btn ta-btn-secondary redeem-btn redeem-btn-secondary" id="redeem-keys-export-btn">导出当前筛选 CSV</button>
            </div>
        </div>

        <p class="ta-help-text">说明：批量软删除仅将状态标记为 <code>deleted</code>，不会物理删除数据库记录。</p>

        <div class="redeem-filter-stack">
            <div class="redeem-filter-bar">
                <label class="redeem-field">
                    <span class="redeem-field-label">状态</span>
                    <select id="redeem-keys-filter-status">
                        <option value="">全部</option>
                        <option value="available">available</option>
                        <option value="revoked">revoked</option>
                        <option value="deleted">deleted</option>
                    </select>
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">分类</span>
                    <select id="redeem-keys-filter-category">
                        <option value="">全部</option>
                    </select>
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">批次</span>
                    <select id="redeem-keys-filter-batch">
                        <option value="">全部</option>
                    </select>
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">渠道</span>
                    <input type="text" id="redeem-keys-filter-channel" placeholder="如 sponsor / bilibili">
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">关键字</span>
                    <input type="text" id="redeem-keys-filter-q" placeholder="按卡密 / 备注 / 批次筛选">
                </label>
            </div>
            <div class="redeem-filter-bar is-advanced">
                <label class="redeem-field">
                    <span class="redeem-field-label">限定玩家 UUID</span>
                    <input type="text" id="redeem-keys-filter-bound-player-uuid" placeholder="精确匹配">
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">允许服务器 ID</span>
                    <input type="text" id="redeem-keys-filter-allowed-server-id" placeholder="按服务器规则筛选">
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">要求绑定账号</span>
                    <select id="redeem-keys-filter-require-bound-account">
                        <option value="">全部</option>
                        <option value="1">是</option>
                        <option value="0">否</option>
                    </select>
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">要求邮箱验证</span>
                    <select id="redeem-keys-filter-require-email-verified">
                        <option value="">全部</option>
                        <option value="1">是</option>
                        <option value="0">否</option>
                    </select>
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">要求账号正常</span>
                    <select id="redeem-keys-filter-require-account-active">
                        <option value="">全部</option>
                        <option value="1">是</option>
                        <option value="0">否</option>
                    </select>
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">创建开始</span>
                    <input type="datetime-local" id="redeem-keys-filter-created-from">
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">创建结束</span>
                    <input type="datetime-local" id="redeem-keys-filter-created-to">
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">过期开始</span>
                    <input type="datetime-local" id="redeem-keys-filter-expires-from">
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">过期结束</span>
                    <input type="datetime-local" id="redeem-keys-filter-expires-to">
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">每页</span>
                    <select id="redeem-keys-filter-per-page">
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="redeem-table-shell ta-table-wrap">
            <table class="ta-table" id="redeem-keys-table">
                <thead>
                <tr>
                    <th><input type="checkbox" id="redeem-keys-select-all"></th>
                    <th>ID</th>
                    <th>卡密</th>
                    <th>分类</th>
                    <th>批次</th>
                    <th>渠道</th>
                    <th>限制服务器</th>
                    <th>限定玩家</th>
                    <th>绑定要求</th>
                    <th>玩家限制</th>
                    <th>账号限制</th>
                    <th>规则备注</th>
                    <th>状态</th>
                    <th>次数</th>
                    <th>创建时间</th>
                    <th>过期时间</th>
                    <th>备注</th>
                    <th>操作</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="redeem-pagination" id="redeem-keys-pagination">
            <button type="button" class="ta-btn ta-btn-secondary" id="redeem-keys-prev">Prev</button>
            <span class="ta-help-text" id="redeem-keys-page-text">Page 1 / 1</span>
            <button type="button" class="ta-btn ta-btn-secondary" id="redeem-keys-next">Next</button>
        </div>
    </section>

    <section class="redeem-section-card">
        <div class="redeem-section-head">
            <div>
                <h2 class="redeem-section-title">批次管理</h2>
                <p class="redeem-section-subtitle">查看批次分发效果并按渠道/关键字检索。</p>
            </div>
            <div class="redeem-actions-row">
                <button type="button" class="ta-btn ta-btn-secondary" id="redeem-batches-refresh-btn">刷新</button>
            </div>
        </div>

        <div class="redeem-filter-bar">
            <label class="redeem-field">
                <span class="redeem-field-label">渠道</span>
                <input type="text" id="redeem-batches-filter-channel" placeholder="按渠道筛选">
            </label>
            <label class="redeem-field">
                <span class="redeem-field-label">关键字</span>
                <input type="text" id="redeem-batches-filter-q" placeholder="批次编号/名称">
            </label>
            <label class="redeem-field">
                <span class="redeem-field-label">每页</span>
                <select id="redeem-batches-filter-per-page">
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </label>
            <label class="redeem-field">
                <span class="redeem-field-label">创建开始</span>
                <input type="datetime-local" id="redeem-batches-filter-created-from">
            </label>
        </div>

        <div class="redeem-table-shell ta-table-wrap">
            <table class="ta-table" id="redeem-batches-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>批次编号</th>
                    <th>批次名称</th>
                    <th>渠道</th>
                    <th>分类</th>
                    <th>数量</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="redeem-pagination" id="redeem-batches-pagination">
            <button type="button" class="ta-btn ta-btn-secondary" id="redeem-batches-prev">Prev</button>
            <span class="ta-help-text" id="redeem-batches-page-text">Page 1 / 1</span>
            <button type="button" class="ta-btn ta-btn-secondary" id="redeem-batches-next">Next</button>
        </div>
    </section>

    <div class="redeem-two-grid">
        <section class="redeem-section-card">
            <div class="redeem-section-head">
                <div>
                    <h2 class="redeem-section-title">新增分类</h2>
                    <p class="redeem-section-subtitle">创建可复用的命令模板分类。</p>
                </div>
            </div>
            <form id="redeem-category-create-form" class="space-y-3">
                <div class="redeem-form-grid">
                    <label class="redeem-field">
                        <span class="redeem-field-label">分类名称</span>
                        <input type="text" name="name" maxlength="128" required>
                    </label>
                    <label class="redeem-field">
                        <span class="redeem-field-label">状态</span>
                        <select name="status">
                            <option value="enabled">enabled</option>
                            <option value="disabled">disabled</option>
                        </select>
                    </label>
                </div>
                <label class="redeem-field">
                    <span class="redeem-field-label">描述</span>
                    <input type="text" name="description">
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">默认命令模板</span>
                    <textarea name="defaultCommandTemplate" rows="4" required></textarea>
                </label>
                <div class="redeem-actions-row">
                    <button type="submit" class="ta-btn ta-btn-primary">新增分类</button>
                </div>
            </form>
        </section>

        <section class="redeem-section-card">
            <div class="redeem-section-head">
                <div>
                    <h2 class="redeem-section-title">分类管理</h2>
                    <p class="redeem-section-subtitle">编辑分类名称、状态、描述和命令模板。</p>
                </div>
            </div>
            <div class="redeem-table-shell ta-table-wrap">
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
        </section>
    </div>

    <section class="redeem-section-card">
        <div class="redeem-section-head">
            <div>
                <h2 class="redeem-section-title">兑换日志</h2>
                <p class="redeem-section-subtitle">按执行结果与人工状态追踪兑换链路。</p>
            </div>
            <div class="redeem-actions-row">
                <button type="button" class="ta-btn ta-btn-secondary" id="redeem-logs-refresh-btn">刷新</button>
            </div>
        </div>
        <p class="ta-help-text">提示：failed 且人工状态为 pending 的记录需要管理员人工核对。V2 仍不会自动回滚命令或 used_count。</p>

        <div class="redeem-filter-stack">
            <div class="redeem-filter-bar">
                <label class="redeem-field">
                    <span class="redeem-field-label">状态</span>
                    <select id="redeem-logs-filter-status">
                        <option value="">全部</option>
                        <option value="executing">executing</option>
                        <option value="success">success</option>
                        <option value="failed">failed</option>
                    </select>
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">人工状态</span>
                    <select id="redeem-logs-filter-admin-status">
                        <option value="">全部</option>
                        <option value="pending">pending</option>
                        <option value="handled">handled</option>
                        <option value="ignored">ignored</option>
                    </select>
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">分类</span>
                    <select id="redeem-logs-filter-category">
                        <option value="">全部</option>
                    </select>
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">规则结果</span>
                    <select id="redeem-logs-filter-rule-result">
                        <option value="">全部</option>
                        <option value="passed">passed</option>
                        <option value="rejected">rejected</option>
                        <option value="skipped">skipped</option>
                    </select>
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">规则原因</span>
                    <input type="text" id="redeem-logs-filter-rule-reason" placeholder="如 server_not_allowed">
                </label>
            </div>
            <div class="redeem-filter-bar is-advanced">
                <label class="redeem-field">
                    <span class="redeem-field-label">网站用户ID</span>
                    <input type="number" id="redeem-logs-filter-website-user-id" min="1" placeholder="精确匹配">
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">服务器ID</span>
                    <input type="text" id="redeem-logs-filter-server-id" placeholder="精确筛选">
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">玩家 UUID</span>
                    <input type="text" id="redeem-logs-filter-player-uuid">
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">玩家名</span>
                    <input type="text" id="redeem-logs-filter-player-name">
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">关键字</span>
                    <input type="text" id="redeem-logs-filter-q" placeholder="玩家/UUID/服务器/批次/渠道">
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">创建开始</span>
                    <input type="datetime-local" id="redeem-logs-filter-created-from">
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">创建结束</span>
                    <input type="datetime-local" id="redeem-logs-filter-created-to">
                </label>
                <label class="redeem-field">
                    <span class="redeem-field-label">每页</span>
                    <select id="redeem-logs-filter-per-page">
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="redeem-table-shell ta-table-wrap">
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
                    <th>人工状态</th>
                    <th>规则结果</th>
                    <th>规则原因</th>
                    <th>网站用户ID</th>
                    <th>批次/渠道</th>
                    <th>失败原因</th>
                    <th>管理员备注</th>
                    <th>规则快照</th>
                    <th>命令快照</th>
                    <th>操作</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="redeem-pagination" id="redeem-logs-pagination">
            <button type="button" class="ta-btn ta-btn-secondary" id="redeem-logs-prev">Prev</button>
            <span class="ta-help-text" id="redeem-logs-page-text">Page 1 / 1</span>
            <button type="button" class="ta-btn ta-btn-secondary" id="redeem-logs-next">Next</button>
        </div>
    </section>

    <section class="redeem-section-card">
        <div class="redeem-section-head">
            <div>
                <h2 class="redeem-section-title">管理员操作日志</h2>
                <p class="redeem-section-subtitle">审计后台卡密系统的管理行为。</p>
            </div>
            <div class="redeem-actions-row">
                <button type="button" class="ta-btn ta-btn-secondary" id="redeem-admin-logs-refresh-btn">刷新</button>
            </div>
        </div>

        <div class="redeem-filter-bar">
            <label class="redeem-field">
                <span class="redeem-field-label">动作</span>
                <input type="text" id="redeem-admin-logs-filter-action" placeholder="如 batch_generate">
            </label>
            <label class="redeem-field">
                <span class="redeem-field-label">对象类型</span>
                <input type="text" id="redeem-admin-logs-filter-target-type" placeholder="如 key / batch / redeem_log">
            </label>
            <label class="redeem-field">
                <span class="redeem-field-label">关键字</span>
                <input type="text" id="redeem-admin-logs-filter-q" placeholder="管理员ID/对象ID/IP/详情">
            </label>
            <label class="redeem-field">
                <span class="redeem-field-label">每页</span>
                <select id="redeem-admin-logs-filter-per-page">
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </label>
            <label class="redeem-field">
                <span class="redeem-field-label">开始时间</span>
                <input type="datetime-local" id="redeem-admin-logs-filter-created-from">
            </label>
            <label class="redeem-field">
                <span class="redeem-field-label">结束时间</span>
                <input type="datetime-local" id="redeem-admin-logs-filter-created-to">
            </label>
        </div>

        <div class="redeem-table-shell ta-table-wrap">
            <table class="ta-table" id="redeem-admin-logs-table">
                <thead>
                <tr>
                    <th>时间</th>
                    <th>管理员ID</th>
                    <th>动作</th>
                    <th>对象类型</th>
                    <th>对象ID</th>
                    <th>详情</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="redeem-pagination" id="redeem-admin-logs-pagination">
            <button type="button" class="ta-btn ta-btn-secondary" id="redeem-admin-logs-prev">Prev</button>
            <span class="ta-help-text" id="redeem-admin-logs-page-text">Page 1 / 1</span>
            <button type="button" class="ta-btn ta-btn-secondary" id="redeem-admin-logs-next">Next</button>
        </div>
    </section>
</div>

<script>
(function () {
    var root = document.getElementById('redeem-admin-root');
    if (!root) return;

    var headerRefresh = document.getElementById('redeem-header-refresh-btn');
    var headerExport = document.getElementById('redeem-header-export-btn');
    var keysRefresh = document.getElementById('redeem-keys-refresh-btn');
    var keysExport = document.getElementById('redeem-keys-export-btn');

    if (headerRefresh && keysRefresh) {
        headerRefresh.addEventListener('click', function () {
            keysRefresh.click();
        });
    }

    if (headerExport && keysExport) {
        headerExport.addEventListener('click', function () {
            keysExport.click();
        });
    }

    var statusTextMap = {
        available: '可用',
        revoked: '已吊销',
        deleted: '已删除',
        executing: '执行中',
        success: '成功',
        failed: '失败',
        pending: '待处理',
        handled: '已处理',
        ignored: '已忽略'
    };

    function statusClass(status) {
        var v = String(status || '').toLowerCase().trim();
        if (!v) return '';
        return 'redeem-status-' + v.replace(/[^a-z0-9_-]/g, '-');
    }

    function beautifyStatusCells(tableId, statusColIndexes) {
        var tbody = document.querySelector('#' + tableId + ' tbody');
        if (!tbody) return;
        var rows = tbody.querySelectorAll('tr');
        rows.forEach(function (row) {
            statusColIndexes.forEach(function (idx) {
                var cell = row.children[idx];
                if (!cell) return;
                var raw = String(cell.textContent || '').trim();
                if (!raw || raw === '-') return;
                if (cell.querySelector('.redeem-status-pill')) return;
                var key = raw.toLowerCase();
                var display = statusTextMap[key] || raw;
                var pill = document.createElement('span');
                pill.className = 'redeem-status-pill ' + statusClass(key);
                pill.setAttribute('data-status', key);
                pill.textContent = display;
                cell.textContent = '';
                cell.appendChild(pill);
            });
        });
    }

    function runStatusBeautify() {
        beautifyStatusCells('redeem-keys-table', [12]);
        beautifyStatusCells('redeem-logs-table', [6, 7]);
    }

    var observer = new MutationObserver(function () {
        runStatusBeautify();
    });

    ['redeem-keys-table', 'redeem-logs-table'].forEach(function (tableId) {
        var tbody = document.querySelector('#' + tableId + ' tbody');
        if (tbody) {
            observer.observe(tbody, { childList: true, subtree: true });
        }
    });

    runStatusBeautify();
})();
</script>

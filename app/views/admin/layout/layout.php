<?php
$renderAdminPagination = static function (array $meta, string $tab): void {
    $currentPage = (int)($meta['page'] ?? 1);
    $totalPages = (int)($meta['total_pages'] ?? 1);
    $param = (string)($meta['param'] ?? 'page');

    if ($totalPages <= 1) {
        return;
    }

    $buildUrl = static function (int $page) use ($tab, $param): string {
        $query = $_GET;
        $query['tab'] = $tab;
        $query[$param] = $page;
        return '/admin?' . http_build_query($query);
    };

    $prevPage = max(1, $currentPage - 1);
    $nextPage = min($totalPages, $currentPage + 1);
    ?>
    <div class="mt-4 flex items-center justify-end gap-2">
        <a href="<?= htmlspecialchars($buildUrl($prevPage), ENT_QUOTES, 'UTF-8') ?>" class="ta-btn ta-btn-secondary <?= $currentPage <= 1 ? 'pointer-events-none opacity-50' : '' ?>">Prev</a>
        <span class="ta-help-text">Page <?= $currentPage ?> / <?= $totalPages ?></span>
        <a href="<?= htmlspecialchars($buildUrl($nextPage), ENT_QUOTES, 'UTF-8') ?>" class="ta-btn ta-btn-secondary <?= $currentPage >= $totalPages ? 'pointer-events-none opacity-50' : '' ?>">Next</a>
    </div>
    <?php
};

$userCount = (int)($userCount ?? 0);
$announcementCount = (int)($announcementCount ?? 0);
$galleryCount = (int)($galleryCount ?? 0);
$players = is_array($players ?? null) ? $players : [];
$feedbackList = is_array($feedbackList ?? null) ? $feedbackList : [];
$announcements = is_array($announcements ?? null) ? $announcements : [];
$milestones = is_array($milestones ?? null) ? $milestones : [];
$images = is_array($images ?? null) ? $images : [];
$siteSettings = is_array($siteSettings ?? null) ? $siteSettings : [];
$teamMembers = is_array($teamMembers ?? null) ? $teamMembers : [];
$ipWhitelist = is_array($ipWhitelist ?? null) ? $ipWhitelist : [];
$ipBlacklist = is_array($ipBlacklist ?? null) ? $ipBlacklist : [];
$playersPagination = is_array($playersPagination ?? null) ? $playersPagination : [];
$feedbackPagination = is_array($feedbackPagination ?? null) ? $feedbackPagination : [];
$feedbackFilters = is_array($feedbackFilters ?? null) ? $feedbackFilters : ['q' => '', 'status' => '', 'category' => ''];
$feedbackLoadError = trim((string)($feedbackLoadError ?? ''));
$announcementsPagination = is_array($announcementsPagination ?? null) ? $announcementsPagination : [];
$milestonesPagination = is_array($milestonesPagination ?? null) ? $milestonesPagination : [];
$imagesPagination = is_array($imagesPagination ?? null) ? $imagesPagination : [];
$teamMembersPagination = is_array($teamMembersPagination ?? null) ? $teamMembersPagination : [];
$ipWhitelistPagination = is_array($ipWhitelistPagination ?? null) ? $ipWhitelistPagination : [];
$ipBlacklistPagination = is_array($ipBlacklistPagination ?? null) ? $ipBlacklistPagination : [];
$realtimePanelEnabled = !empty($realtimePanelEnabled);
$csrfToken = (string)($csrfToken ?? ($_SESSION['csrf_token'] ?? ''));
$csrfTokenEscaped = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');
$feedbackStatusLabels = [
    'pending' => '待处理',
    'reviewing' => '处理中',
    'need_more_info' => '需要补充',
    'resolved' => '已处理',
    'rejected' => '已驳回',
];
$feedbackStatusClassMap = [
    'pending' => 'ta-feedback-status--pending',
    'reviewing' => 'ta-feedback-status--reviewing',
    'need_more_info' => 'ta-feedback-status--need-more-info',
    'resolved' => 'ta-feedback-status--resolved',
    'rejected' => 'ta-feedback-status--rejected',
];
$feedbackCategoryLabels = [
    'report' => '举报玩家',
    'bug' => '漏洞问题',
    'account' => '账号相关',
    'suggestion' => '玩法建议',
    'other' => '其他',
];
?>
<div id="admin-main-content" class="space-y-6">

    <div id="tab-dashboard" class="ta-tab-content">
    <?php if (($_GET['err'] ?? '') === 'csrf'): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700">
            安全校验失败（CSRF）。请刷新后台页面后重试。
        </div>
    <?php endif; ?>
    <!-- MOD: TailAdmin KPI Card Start -->
    <div class="ta-kpi-shell rounded-xl bg-white p-6 shadow-md">
        <h1>后台总览</h1>
        <p class="mt-2 text-sm ta-kpi-label">
            欢迎，<?= htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>（管理员）。
        </p>
        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="ta-kpi-card bg-white rounded-xl shadow-md p-6 flex items-center gap-4">
                <div class="ta-kpi-icon flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div>
                    <h2 class="ta-kpi-label text-sm text-slate-700">玩家总数</h2>
                    <div class="ta-kpi-value text-2xl font-bold text-slate-900"><?= (int)$userCount ?></div>
                </div>
            </div>
            <div class="ta-kpi-card bg-white rounded-xl shadow-md p-6 flex items-center gap-4">
                <div class="ta-kpi-icon flex h-12 w-12 items-center justify-center rounded-lg bg-sky-100 text-sky-600">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M3 11.5 21 5v14l-18-6v-1.5ZM8 13v4.5a2.5 2.5 0 0 0 5 0V14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div>
                    <h2 class="ta-kpi-label text-sm text-slate-700">公告数量</h2>
                    <div class="ta-kpi-value text-2xl font-bold text-slate-900"><?= (int)$announcementCount ?></div>
                </div>
            </div>
            <div class="ta-kpi-card bg-white rounded-xl shadow-md p-6 flex items-center gap-4">
                <div class="ta-kpi-icon flex h-12 w-12 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.5"/>
                        <circle cx="9" cy="10" r="1.5" fill="currentColor"/>
                        <path d="m21 16-5.2-5.2a1.5 1.5 0 0 0-2.1 0L8 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </div>
                <div>
                    <h2 class="ta-kpi-label text-sm text-slate-700">相册图片</h2>
                    <div class="ta-kpi-value text-2xl font-bold text-slate-900"><?= (int)$galleryCount ?></div>
                </div>
            </div>
        </div>
    </div>
    <!-- MOD: TailAdmin KPI Card End -->

        </div>

<!-- Tab 导航 -->
    <div class="space-y-6">
        <?php if (!empty($realtimePanelEnabled)): ?>
            <?php include BASE_PATH . '/app/views/admin/layout/realtime_panel.php'; ?>
        <?php endif; ?>

        <!-- 玩家管理 Tab -->
        <!-- Check-in Rewards Tab -->
        <div id="tab-checkin-rewards" class="ta-tab-content tab-hidden">
            <?php include BASE_PATH . '/app/views/admin/checkin/rewards.php'; ?>
        </div>

        <!-- Check-in Logs Tab -->
        <div id="tab-checkin-logs" class="ta-tab-content tab-hidden">
            <?php include BASE_PATH . '/app/views/admin/checkin/logs.php'; ?>
        </div>

        <!-- Check-in Stats Tab -->
        <div id="tab-checkin-stats" class="ta-tab-content tab-hidden">
            <?php include BASE_PATH . '/app/views/admin/checkin/stats.php'; ?>
        </div>

        <div id="tab-players" class="ta-tab-content tab-hidden">
            <div class="ta-card">
                <h1>玩家管理</h1>
                <p>
                    可在此对玩家进行角色调整、冻结（封禁）或删除账号操作。
                </p>
                
                <div class="ta-table-wrap">
                    <table class="ta-table ta-table-wide">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>账号信息</th>
                            <th>游戏绑定</th>
                            <th>权限管理</th>
                            <th>近期活动</th>
                            <th>注册记录</th>
                            <th>操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        $formatAdminUnixTs = static function ($v): string {
                            if ($v === null || $v === '') return '—';
                            if (!is_numeric($v)) return '—';
                            $ts = (int)$v;
    
                            // --- 兼容 AuthMe 毫秒级时间戳 ---
                            // 标准秒级时间戳最大 10 位数 (直到 2286 年)
                            // 如果数字大于 9999999999，说明它是毫秒，需要除以 1000
                            if ($ts > 9999999999) {
                                $ts = (int)($ts / 1000);
                            }
    
                            return $ts > 0 ? date('Y-m-d H:i:s', $ts) : '—';
                        };
                        $formatAdminIpCell = static function ($v): string {
                            $t = trim((string)($v ?? ''));
                            return $t === '' ? '—' : htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
                        };
                        ?>
                        <?php foreach ($players as $p): ?>
                            <?php
                            $mcName = trim((string)($p['mc_username'] ?? ''));
                            $mcUuid = trim((string)($p['mc_uuid'] ?? ''));
                            $formId = 'player-update-' . (int)$p['id'];
                            ?>
                            <tr>
                                <td>#<?= (int)$p['id'] ?></td>
                                
                                <td>
                                    <div><?= htmlspecialchars($p['username'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div><?= htmlspecialchars($p['email'], ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                
                                <td>
                                    <div class="ta-stack ta-stack-sm ta-stack-limit">
                                        <input type="text" name="mc_username" form="<?= $formId ?>" value="<?= htmlspecialchars($mcName, ENT_QUOTES, 'UTF-8') ?>" placeholder="游戏名 (留空解绑)" aria-label="Minecraft username">
                                        <input type="text" name="mc_uuid" form="<?= $formId ?>" value="<?= htmlspecialchars($mcUuid, ENT_QUOTES, 'UTF-8') ?>" placeholder="UUID (留空解绑)" aria-label="Minecraft UUID">
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="ta-stack ta-stack-sm">
                                        <select name="role" form="<?= $formId ?>" aria-label="User role">
                                            <option value="player" <?= $p['role']==='player'?'selected':''; ?>>👤 玩家</option>
                                            <option value="admin" <?= $p['role']==='admin'?'selected':''; ?>>🛡️ 管理员</option>
                                        </select>
                                        <select name="status" form="<?= $formId ?>" aria-label="User status">
                                            <option value="active" <?= $p['status']==='active'?'selected':''; ?>>🟢 正常</option>
                                            <option value="frozen" <?= $p['status']==='frozen'?'selected':''; ?>>❄️ 冻结</option>
                                            <option value="banned" <?= $p['status']==='banned'?'selected':''; ?>>⛔ 封禁</option>
                                        </select>
                                    </div>
                                </td>
                                
                                <td>
                                    <div><i class="mdi mdi-earth"></i> <?= $formatAdminIpCell($p['ip'] ?? null) ?></div>
                                    <div><i class="mdi mdi-clock-outline"></i> <?= htmlspecialchars($formatAdminUnixTs($p['lastlogin'] ?? null), ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                
                                <td>
                                    <div><i class="mdi mdi-earth"></i> <?= $formatAdminIpCell($p['regip'] ?? null) ?></div>
                                    <div><i class="mdi mdi-clock-outline"></i> <?= htmlspecialchars($formatAdminUnixTs($p['regdate'] ?? null), ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                
                                <td>
                                    <div class="ta-action-stack">
                                        <form id="<?= $formId ?>" method="post" action="/admin/players/update">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfTokenEscaped ?>">
                                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                            <button type="submit" class="ta-btn ta-btn-secondary">保存</button>
                                        </form>
                                        <form method="post" action="/admin/players/delete" onsubmit="return confirm('确定要删除该玩家账号吗？此操作不可撤销。');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfTokenEscaped ?>">
                                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                            <button type="submit" class="ta-btn ta-btn-primary">删除</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php $renderAdminPagination($playersPagination ?? [], 'players'); ?>
            </div>
        </div>

        <!-- 公告管理 Tab -->
        <div id="tab-feedback" class="ta-tab-content tab-hidden">
            <?php
            $feedbackQuery = trim((string)($feedbackFilters['q'] ?? ''));
            $feedbackStatusFilter = strtolower(trim((string)($feedbackFilters['status'] ?? '')));
            $feedbackCategoryFilter = strtolower(trim((string)($feedbackFilters['category'] ?? '')));
            $formatFeedbackTime = static function ($value): string {
                $text = trim((string)$value);
                if ($text === '') {
                    return '--';
                }
                $ts = strtotime($text);
                return $ts === false ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8') : date('Y-m-d H:i:s', $ts);
            };
            $feedbackErr = trim((string)($_GET['err'] ?? ''));
            ?>
            <div class="ta-card ta-feedback-card">
                <h1>举报反馈工单</h1>
                <p class="ta-help-text">管理员可以在此检索玩家反馈、查看证据并更新处理状态。</p>

                <?php if (isset($_GET['saved']) && (string)$_GET['saved'] === '1'): ?>
                    <div class="ta-feedback-alert ta-feedback-alert--success">反馈状态已更新。</div>
                <?php endif; ?>

                <?php if ($feedbackErr !== ''): ?>
                    <div class="ta-feedback-alert ta-feedback-alert--error">
                        <?php
                        $feedbackErrMap = [
                            'csrf' => 'CSRF 校验失败，请刷新后重试。',
                            'feedback_id' => '反馈编号无效。',
                            'status' => '状态参数不合法。',
                            'reply_len' => '管理员回复过长（最多 5000 字）。',
                            'reply_required' => '当状态为“需要补充”时，请填写管理员回复，说明需要补充哪些材料。',
                            'not_found' => '反馈记录不存在或已变更。',
                            'auth' => '权限验证失败。',
                            'save' => '保存失败，请稍后重试。',
                        ];
                        echo htmlspecialchars($feedbackErrMap[$feedbackErr] ?? ('操作失败：' . $feedbackErr), ENT_QUOTES, 'UTF-8');
                        ?>
                    </div>
                <?php endif; ?>

                <?php if ($feedbackLoadError !== ''): ?>
                    <div class="ta-feedback-alert ta-feedback-alert--error">
                        <?= htmlspecialchars($feedbackLoadError, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="get" action="/admin" class="ta-feedback-filters">
                    <input type="hidden" name="tab" value="feedback">
                    <div>
                        <label for="feedback-q">关键词</label>
                        <input id="feedback-q" type="text" name="feedback_q" value="<?= htmlspecialchars($feedbackQuery, ENT_QUOTES, 'UTF-8') ?>" placeholder="搜索玩家、标题、内容">
                    </div>
                    <div>
                        <label for="feedback-status">状态</label>
                        <select id="feedback-status" name="feedback_status">
                            <option value="">全部状态</option>
                            <?php foreach ($feedbackStatusLabels as $statusKey => $statusLabel): ?>
                                <option value="<?= htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') ?>" <?= $feedbackStatusFilter === $statusKey ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="feedback-category">类型</label>
                        <select id="feedback-category" name="feedback_category">
                            <option value="">全部类型</option>
                            <?php foreach ($feedbackCategoryLabels as $categoryKey => $categoryLabel): ?>
                                <option value="<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>" <?= $feedbackCategoryFilter === $categoryKey ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ta-feedback-filter-actions">
                        <button type="submit" class="ta-btn ta-btn-primary">搜索</button>
                        <a href="/admin?tab=feedback" class="ta-btn ta-btn-secondary">重置</a>
                    </div>
                </form>

                <div class="ta-table-wrap">
                    <table class="ta-table ta-feedback-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>玩家</th>
                            <th>游戏名</th>
                            <th>类型</th>
                            <th>标题</th>
                            <th>状态</th>
                            <th>创建时间</th>
                            <th>更新时间</th>
                            <th>操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($feedbackList)): ?>
                            <tr>
                                <td colspan="9" class="ta-help-text">暂无符合条件的反馈记录。</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($feedbackList as $feedbackItem): ?>
                                <?php
                                $feedbackId = (int)($feedbackItem['id'] ?? 0);
                                $feedbackUsername = (string)($feedbackItem['username'] ?? '');
                                $feedbackMcUsername = (string)($feedbackItem['mc_username'] ?? '');
                                $feedbackCategory = strtolower(trim((string)($feedbackItem['category'] ?? 'other')));
                                $feedbackCategoryLabel = $feedbackCategoryLabels[$feedbackCategory] ?? $feedbackCategory;
                                $feedbackTitle = (string)($feedbackItem['title'] ?? '');
                                $feedbackStatus = strtolower(trim((string)($feedbackItem['status'] ?? 'pending')));
                                $feedbackStatusLabel = $feedbackStatusLabels[$feedbackStatus] ?? $feedbackStatus;
                                $feedbackStatusClass = $feedbackStatusClassMap[$feedbackStatus] ?? $feedbackStatusClassMap['pending'];
                                $feedbackReply = trim((string)($feedbackItem['admin_reply'] ?? ''));
                                $feedbackTargetPlayer = trim((string)($feedbackItem['target_player'] ?? ''));
                                $feedbackOccurredAt = trim((string)($feedbackItem['occurred_at'] ?? ''));
                                $feedbackWorld = trim((string)($feedbackItem['world'] ?? ''));
                                $feedbackCoordinates = trim((string)($feedbackItem['coordinates'] ?? ''));
                                $feedbackContent = trim((string)($feedbackItem['content'] ?? ''));
                                $feedbackEvidence = trim((string)($feedbackItem['evidence_url'] ?? ''));
                                $feedbackUserSupplement = trim((string)($feedbackItem['user_supplement'] ?? ''));
                                $feedbackAttachments = is_array($feedbackItem['attachments'] ?? null) ? $feedbackItem['attachments'] : [];
                                ?>
                                <tr>
                                    <td>#<?= $feedbackId ?></td>
                                    <td><?= htmlspecialchars($feedbackUsername, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($feedbackMcUsername !== '' ? $feedbackMcUsername : '--', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($feedbackCategoryLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($feedbackTitle, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="ta-feedback-status <?= htmlspecialchars($feedbackStatusClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($feedbackStatusLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td><?= $formatFeedbackTime($feedbackItem['created_at'] ?? '') ?></td>
                                    <td><?= $formatFeedbackTime($feedbackItem['updated_at'] ?? '') ?></td>
                                    <td class="ta-feedback-cell-actions">
                                        <details class="ta-feedback-details">
                                            <summary class="ta-btn ta-btn-secondary">展开详情</summary>
                                            <div class="ta-feedback-detail-body">
                                                <p><strong>被举报玩家：</strong><?= htmlspecialchars($feedbackTargetPlayer !== '' ? $feedbackTargetPlayer : '--', ENT_QUOTES, 'UTF-8') ?></p>
                                                <p><strong>发生时间：</strong><?= htmlspecialchars($feedbackOccurredAt !== '' ? $feedbackOccurredAt : '--', ENT_QUOTES, 'UTF-8') ?></p>
                                                <p><strong>世界：</strong><?= htmlspecialchars($feedbackWorld !== '' ? $feedbackWorld : '--', ENT_QUOTES, 'UTF-8') ?></p>
                                                <p><strong>坐标：</strong><?= htmlspecialchars($feedbackCoordinates !== '' ? $feedbackCoordinates : '--', ENT_QUOTES, 'UTF-8') ?></p>
                                                <p><strong>详细内容：</strong></p>
                                                <div class="ta-feedback-content"><?= nl2br(htmlspecialchars($feedbackContent, ENT_QUOTES, 'UTF-8')) ?></div>
                                                <?php if ($feedbackUserSupplement !== ''): ?>
                                                    <p><strong>玩家补充内容：</strong></p>
                                                    <div class="ta-feedback-content"><?= nl2br(htmlspecialchars($feedbackUserSupplement, ENT_QUOTES, 'UTF-8')) ?></div>
                                                <?php endif; ?>
                                                <p>
                                                    <strong>证据链接：</strong>
                                                    <?php if ($feedbackEvidence !== ''): ?>
                                                        <a href="<?= htmlspecialchars($feedbackEvidence, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($feedbackEvidence, ENT_QUOTES, 'UTF-8') ?></a>
                                                    <?php else: ?>
                                                        --
                                                    <?php endif; ?>
                                                </p>
                                                <?php if (!empty($feedbackAttachments)): ?>
                                                    <div class="ta-feedback-attachments">
                                                        <?php foreach ($feedbackAttachments as $attachment): ?>
                                                            <?php
                                                            $attachmentPath = trim((string)($attachment['file_path'] ?? ''));
                                                            if ($attachmentPath === '') {
                                                                continue;
                                                            }
                                                            if ($attachmentPath[0] !== '/') {
                                                                $attachmentPath = '/' . ltrim($attachmentPath, '/');
                                                            }
                                                            ?>
                                                            <a href="<?= htmlspecialchars($attachmentPath, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
                                                                <img src="<?= htmlspecialchars($attachmentPath, ENT_QUOTES, 'UTF-8') ?>" alt="反馈附件" loading="lazy">
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </details>

                                        <form method="post" action="/admin/feedback/update" class="ta-feedback-update-form" data-feedback-admin-form>
                                            <input type="hidden" name="csrf_token" value="<?= $csrfTokenEscaped ?>">
                                            <input type="hidden" name="feedback_id" value="<?= $feedbackId ?>">
                                            <label for="feedback-status-<?= $feedbackId ?>">状态</label>
                                            <select id="feedback-status-<?= $feedbackId ?>" name="status" data-feedback-admin-status>
                                                <?php foreach ($feedbackStatusLabels as $statusKey => $statusLabel): ?>
                                                    <option value="<?= htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') ?>" <?= $feedbackStatus === $statusKey ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label for="feedback-reply-<?= $feedbackId ?>">管理员回复</label>
                                            <textarea id="feedback-reply-<?= $feedbackId ?>" name="admin_reply" rows="3" maxlength="5000" placeholder="可填写处理说明或补充要求" data-feedback-admin-reply><?= htmlspecialchars($feedbackReply, ENT_QUOTES, 'UTF-8') ?></textarea>
                                            <p class="ta-help-text" data-feedback-admin-hint>当状态选择“需要补充”时，建议填写需要玩家补充的具体材料。</p>
                                            <button type="submit" class="ta-btn ta-btn-primary">保存</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php $renderAdminPagination($feedbackPagination ?? [], 'feedback'); ?>
            </div>
        </div>

        <div id="tab-announcements" class="ta-tab-content tab-hidden">
            <div class="ta-card">
                <h1>公告管理</h1>
                <p>
                    你可以在此创建或编辑网站公告，公告内容支持简单换行（Markdown 可在后续扩展为前端渲染）。
                </p>

                <h2>现有公告</h2>
                <table class="ta-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>标题</th>
                        <th>状态</th>
                        <th>更新时间</th>
                        <th>操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php $nowTs = time(); ?>
                    <?php foreach ($announcements as $a): ?>
                        <tr>
                            <td><?= (int)$a['id'] ?></td>
                            <td>
                                <?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <?php
                                $isPub = (int)($a['is_published'] ?? 0) === 1;
                                $createdAt = (string)($a['created_at'] ?? '');
                                $createdTs = $createdAt !== '' ? strtotime($createdAt) : false;
                                if (!$isPub) {
                                    echo '草稿';
                                } elseif ($createdTs !== false && $createdTs > $nowTs) {
                                    echo '定时发布 (' . htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') . ')';
                                } else {
                                    echo '已发布';
                                }
                                ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($a['updated_at'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <button type="button" class="ta-btn ta-btn-secondary"
                                        onclick='editAnnouncement(<?= (int)$a['id'] ?>, <?= json_encode($a['title'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($a['content'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>, <?= (int)($a['is_published'] ?? 0) ?>, <?= json_encode($a['created_at'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)'>编辑</button>
                                <form method="post" action="/admin/announcements/delete" onsubmit="return confirm('确定要永久删除此公告吗？');">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfTokenEscaped ?>">
                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                    <button type="submit" class="ta-btn ta-btn-secondary">删除</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <h2>新建 / 编辑公告</h2>
                <?php $renderAdminPagination($announcementsPagination ?? [], 'announcements'); ?>
                <form method="post" action="/admin/announcements/save">
                    <input type="hidden" name="csrf_token" value="<?= $csrfTokenEscaped ?>">
                    <input type="hidden" name="id" value="">
                    <div>
                        <label for="announcement-title">标题</label>
                        <input id="announcement-title" name="title" type="text" required
                               >
                    </div>
                    <div>
                        <label for="announcement-content">内容</label>
                        <textarea id="announcement-content" name="content" rows="6" required
                                  ></textarea>
                    </div>
                    <div>
                        <label class="cntr">
                            <input type="checkbox" name="is_published" value="1" checked class="cbx-input">
                            <span class="cbx" aria-hidden="true"></span>
                            <span class="lbl">
                                发布（不勾选则保存为草稿）
                            </span>
                        </label>
                    </div>
                    <div>
                        <div class="ta-inline-options">
                            <label>
                                <input type="radio" name="publish_mode" value="immediate" checked>
                                立即发布
                            </label>
                            <label>
                                <input type="radio" name="publish_mode" value="scheduled">
                                定时发布
                            </label>
                            <input type="datetime-local" name="publish_time" class="ta-datetime-input">
                        </div>
                        <div>
                            选择“定时发布”后可设置未来时间；前端仅展示已到发布时间的公告。
                        </div>
                    </div>
                    <button type="submit" class="ta-btn ta-btn-primary" id="announcement-submit-btn">保存公告</button>
                </form>
            </div>
        </div>

        <!-- 发展纪事 Tab -->
        <div id="tab-milestones" class="ta-tab-content tab-hidden">
            <div class="ta-card">
                <h1>发展纪事管理</h1>
                <p>
                    你可以在此添加、编辑或删除服务器的发展纪事，用于在「服主主页」展示时间轴。
                </p>

                <h2>现有纪事</h2>
                <table class="ta-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>时间</th>
                        <th>内容</th>
                        <th>创建时间</th>
                        <th>操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($milestones as $m): ?>
                        <tr>
                            <td><?= (int)$m['id'] ?></td>
                            <td>
                                <?= htmlspecialchars((string)($m['milestone_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <?php
                                $raw = (string)($m['description'] ?? '');
                                $short = mb_strlen($raw, 'UTF-8') > 60 ? mb_substr($raw, 0, 60, 'UTF-8') . '…' : $raw;
                                echo htmlspecialchars($short, ENT_QUOTES, 'UTF-8');
                                ?>
                            </td>
                            <td>
                                <?= htmlspecialchars((string)($m['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <button type="button" class="ta-btn ta-btn-secondary"
                                        onclick='editMilestone(<?= (int)$m['id'] ?>, <?= json_encode($m['milestone_date'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($m['description'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)'>编辑</button>
                                <form method="post" action="/admin/milestones/delete" onsubmit="return confirm('确定要永久删除此纪事吗？');">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfTokenEscaped ?>">
                                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                    <button type="submit" class="ta-btn ta-btn-secondary">删除</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <h2>新建 / 编辑纪事</h2>
                <?php $renderAdminPagination($milestonesPagination ?? [], 'milestones'); ?>
                <form method="post" action="/admin/milestones/save">
                    <input type="hidden" name="csrf_token" value="<?= $csrfTokenEscaped ?>">
                    <input type="hidden" name="id" value="">
                    <div>
                        <label>时间（示例：2024年1月）</label>
                        <input name="milestone_date" type="text" required
                              >
                    </div>
                    <div>
                        <label>内容</label>
                        <textarea name="description" rows="6" required
                                 ></textarea>
                    </div>
                    <button type="submit" class="ta-btn ta-btn-primary" id="milestone-submit-btn">保存纪事</button>
                </form>
            </div>
        </div>

        <!-- 相册管理 Tab -->
        <div id="tab-gallery" class="ta-tab-content tab-hidden">
            <div class="ta-card">
                <h1>相册管理</h1>
                <p>
                    通过上传 MC 截图并填写描述，更新到前端相册页面。
                </p>

                <h2>上传新截图</h2>
                <form method="post" action="/admin/gallery/upload" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrfTokenEscaped ?>">
                    <div>
                        <label>标题</label>
                        <input name="title" type="text"
                              >
                    </div>
                    <div>
                        <label>描述</label>
                        <textarea name="description" rows="3"
                                 ></textarea>
                    </div>
                    <div>
                        <label>图片文件（建议使用 .png 或 .webp）</label>
                        <input name="image" id="gallery-upload-input" type="file" accept="image/*" required class="ta-hidden-input">
                        <div class="ta-file-row">
                            <label for="gallery-upload-input" class="ta-btn ta-btn-primary">
                                <i class="mdi mdi-cloud-upload"></i>
                                <span>选择图片</span>
                            </label>
                            <span id="gallery-file-name" class="ta-help-text">未选择任何文件</span>
                        </div>
                    </div>
                    <button type="submit" class="ta-btn ta-btn-primary">上传</button>
                </form>

                <h2>现有截图</h2>
                <div class="ta-gallery-grid">
                    <?php foreach ($images as $img): ?>
                        <div class="ta-card ta-gallery-item">
                            <div class="ta-gallery-image-wrap">
                                <img src="<?= htmlspecialchars($img['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars($img['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    >
                            </div>
                            <h3>
                                <?= htmlspecialchars($img['title'] ?? '未命名截图', ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                            <?php if (!empty($img['description'])): ?>
                                <p>
                                    <?= htmlspecialchars($img['description'], ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            <?php endif; ?>
                            <form method="post" action="/admin/gallery/delete" onsubmit="return confirm('确定要永久删除此图片及其文件吗？');">
                                <input type="hidden" name="csrf_token" value="<?= $csrfTokenEscaped ?>">
                                <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
                                <button type="submit" class="ta-btn ta-btn-secondary">删除</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php $renderAdminPagination($imagesPagination ?? [], 'gallery'); ?>
            </div>
        </div>

        <!-- 基础设置 Tab -->
        <div id="tab-site-settings" class="ta-tab-content tab-hidden">
            <div class="ta-card">
                <h1>基础设置</h1>
                <p>
                    动态配置注册与同 IP 策略；白名单 IP 不受「同 IP 注册上限」限制。开启「白名单无视限流」后，白名单 IP 将跳过登录失败锁定、接口 Rate Limit 与会话冷却。
                </p>
                <?php if (empty($siteSettings ?? [])): ?>
                    <p>尚未创建 <code>site_settings</code> 表或无数据，请先执行 <code>database.sql</code> 中的相关段落。</p>
                <?php else: ?>
                <form method="post" action="/admin/site-settings/save">
                    <input type="hidden" name="csrf_token" value="<?= $csrfTokenEscaped ?>">
                    <?php foreach ($siteSettings as $s): ?>
                        <?php
                        $sk = (string)($s['setting_key'] ?? '');
                        $sv = (string)($s['setting_value'] ?? '');
                        $sd = (string)($s['description'] ?? '');
                        if ($sk === '') {
                            continue;
                        }
                        ?>
                        <div>
                            <label>
                                <?= htmlspecialchars($sk, ENT_QUOTES, 'UTF-8') ?>
                            </label>
                            <?php if ($sd !== ''): ?>
                                <p><?= htmlspecialchars($sd, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <?php if ($sk === 'whitelist_ignores_rate_limit' || $sk === 'email_domain_whitelist_enabled'): ?>
                                <select name="settings[<?= htmlspecialchars($sk, ENT_QUOTES, 'UTF-8') ?>]">
                                    <option value="0" <?= $sv === '1' ? '' : 'selected'; ?>>关闭（0）</option>
                                    <option value="1" <?= $sv === '1' ? 'selected' : ''; ?>>开启（1）</option>
                                </select>
                            <?php elseif ($sk === 'register_ip_limit'): ?>
                                <input type="number" name="settings[<?= htmlspecialchars($sk, ENT_QUOTES, 'UTF-8') ?>]" min="0" max="9999" step="1" value="<?= htmlspecialchars($sv, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($sk, ENT_QUOTES, 'UTF-8') ?>"
                                       >
                                <span>0 表示不限制（非白名单 IP）</span>
                            <?php elseif ($sk === 'email_code_expire_seconds'): ?>
                                <input type="number" name="settings[<?= htmlspecialchars($sk, ENT_QUOTES, 'UTF-8') ?>]" min="60" max="3600" step="1" value="<?= htmlspecialchars($sv, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($sk, ENT_QUOTES, 'UTF-8') ?>"
                                       >
                                <span>建议 300-900 秒。</span>
                            <?php elseif ($sk === 'email_code_send_cooldown_seconds'): ?>
                                <input type="number" name="settings[<?= htmlspecialchars($sk, ENT_QUOTES, 'UTF-8') ?>]" min="30" max="3600" step="1" value="<?= htmlspecialchars($sv, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($sk, ENT_QUOTES, 'UTF-8') ?>"
                                       >
                                <span>同一邮箱两次发送之间的最短间隔。</span>
                            <?php elseif ($sk === 'email_domain_whitelist'): ?>
                                <textarea name="settings[<?= htmlspecialchars($sk, ENT_QUOTES, 'UTF-8') ?>]" rows="4" aria-label="<?= htmlspecialchars($sk, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($sv, ENT_QUOTES, 'UTF-8') ?></textarea>
                                <span>多个域名使用英文逗号分隔，例如 <code>qq.com,gmail.com,outlook.com</code>。</span>
                            <?php elseif ($sk === 'audit_log_storage'): ?>
                                <select name="settings[<?= htmlspecialchars($sk, ENT_QUOTES, 'UTF-8') ?>]">
                                    <option value="mysql" <?= $sv === 'mysql' ? 'selected' : ''; ?>>MySQL</option>
                                    <option value="file" <?= $sv === 'file' ? 'selected' : ''; ?>>文件</option>
                                    <option value="both" <?= $sv === 'both' ? 'selected' : ''; ?>>双写</option>
                                </select>
                            <?php else: ?>
                                <input type="text" name="settings[<?= htmlspecialchars($sk, ENT_QUOTES, 'UTF-8') ?>]" value="<?= htmlspecialchars($sv, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($sk, ENT_QUOTES, 'UTF-8') ?>"
                                       >
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" class="ta-btn ta-btn-primary">保存设置</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- 团队管理 Tab -->
        <div id="tab-team" class="ta-tab-content tab-hidden">
            <div class="ta-card">
                <h1>团队管理</h1>
                <p>
                    「关于我们」页面成员列表来自本表；游戏名用于 Minotar 头像，限字母数字与下划线，最长 32 字符。
                </p>
                <?php
                $teamErr = isset($_GET['err']) ? (string)$_GET['err'] : '';
                if ($teamErr === 'invalid_user'): ?>
                    <p>游戏名格式不正确。</p>
                <?php endif; ?>

                <h2>现有成员</h2>
                <div class="ta-table-wrap">
                    <table class="ta-table ta-table-team">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>游戏名</th>
                            <th>角色</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach (($teamMembers ?? []) as $tm): ?>
                            <tr>
                                <td><?= (int)$tm['id'] ?></td>
                                <td><?= htmlspecialchars((string)($tm['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string)($tm['role'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string)($tm['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <button type="button" class="ta-btn ta-btn-secondary"
                                            onclick='editTeamMember(<?= (int)$tm['id'] ?>, <?= json_encode($tm['username'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($tm['role'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)'>编辑</button>
                                    <form method="post" action="/admin/team-members/delete" onsubmit="return confirm('确定从团队中移除该成员吗？');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfTokenEscaped ?>">
                                        <input type="hidden" name="id" value="<?= (int)$tm['id'] ?>">
                                        <button type="submit" class="ta-btn ta-btn-secondary">删除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h2>新建 / 编辑成员</h2>
                <?php $renderAdminPagination($teamMembersPagination ?? [], 'team'); ?>
                <form method="post" action="/admin/team-members/save">
                    <input type="hidden" name="csrf_token" value="<?= $csrfTokenEscaped ?>">
                    <input type="hidden" name="id" value="">
                    <div>
                        <label for="team-member-username">游戏名（Minecraft）</label>
                        <input id="team-member-username" name="username" type="text" required maxlength="32" pattern="[a-zA-Z0-9_]{1,32}"
                               >
                    </div>
                    <div>
                        <label for="team-member-role">角色 / 职位</label>
                        <input id="team-member-role" name="role" type="text" maxlength="128" placeholder="服务器成员"
                               >
                    </div>
                    <button type="submit" class="ta-btn ta-btn-primary" id="team-member-submit-btn">添加成员</button>
                </form>
            </div>
        </div>

        <!-- IP 白名单 Tab -->
        <div id="tab-ip-whitelist" class="ta-tab-content tab-hidden">
            <div class="ta-card">
                <h1>IP 白名单</h1>
                <p>
                    白名单优先级高于黑名单：命中白名单的 IP 可登录/注册/找回密码（不受黑名单拦截），且不受「同 IP 注册上限」限制。是否同时无视 Rate Limit 由基础设置中的开关控制。
                </p>
                <?php
                $wlErr = isset($_GET['err']) ? (string)$_GET['err'] : '';
                if ($wlErr === 'invalid'): ?>
                    <p>格式不正确，请输入合法的 IPv4/IPv6 或 CIDR。</p>
                <?php elseif ($wlErr === 'dup'): ?>
                    <p>该规则已存在。</p>
                <?php endif; ?>

                <h2>当前规则</h2>
                <table class="ta-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>IP / CIDR</th>
                        <th>备注</th>
                        <th>创建时间</th>
                        <th>操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (($ipWhitelist ?? []) as $wl): ?>
                        <tr>
                            <td><?= (int)$wl['id'] ?></td>
                            <td><?= htmlspecialchars((string)($wl['ip_cidr'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($wl['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($wl['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <form method="post" action="/admin/ip-whitelist/delete" onsubmit="return confirm('确定要删除该白名单规则吗？');">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfTokenEscaped ?>">
                                    <input type="hidden" name="id" value="<?= (int)$wl['id'] ?>">
                                    <button type="submit" class="ta-btn ta-btn-secondary">删除</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <h2>添加规则</h2>
                <?php $renderAdminPagination($ipWhitelistPagination ?? [], 'ip-whitelist'); ?>
                <form method="post" action="/admin/ip-whitelist/add">
                    <input type="hidden" name="csrf_token" value="<?= $csrfTokenEscaped ?>">
                    <div>
                        <label for="ip-whitelist-cidr">IP 或 CIDR</label>
                        <input id="ip-whitelist-cidr" name="ip_cidr" type="text" required placeholder="例如 203.0.113.1 或 2001:db8::/32"
                               >
                    </div>
                    <div>
                        <label for="ip-whitelist-reason">备注（可选）</label>
                        <input id="ip-whitelist-reason" name="reason" type="text" maxlength="255"
                               >
                    </div>
                    <button type="submit" class="ta-btn ta-btn-primary">添加</button>
                </form>
            </div>
        </div>

        <!-- IP 黑名单 Tab -->
        <div id="tab-ip-blacklist" class="ta-tab-content tab-hidden">
            <div class="ta-card">
                <h1>IP 黑名单管理</h1>
                <p>
                    仅对登录、注册、找回密码接口生效；支持精确 IP 或 CIDR（如 <code>192.168.1.0/24</code>、<code>2001:db8::/32</code>）。
                </p>
                <?php
                $blErr = isset($_GET['err']) ? (string)$_GET['err'] : '';
                if ($blErr === 'invalid'): ?>
                    <p>格式不正确，请输入合法的 IPv4/IPv6 或 CIDR。</p>
                <?php elseif ($blErr === 'dup'): ?>
                    <p>该规则已存在。</p>
                <?php endif; ?>

                <h2>当前规则</h2>
                <table class="ta-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>IP / CIDR</th>
                        <th>原因</th>
                        <th>创建时间</th>
                        <th>操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (($ipBlacklist ?? []) as $bl): ?>
                        <tr>
                            <td><?= (int)$bl['id'] ?></td>
                            <td><?= htmlspecialchars((string)($bl['ip_cidr'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($bl['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($bl['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <form method="post" action="/admin/ip-blacklist/delete" onsubmit="return confirm('确定要解封该 IP / 网段吗？');">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfTokenEscaped ?>">
                                    <input type="hidden" name="id" value="<?= (int)$bl['id'] ?>">
                                    <button type="submit" class="ta-btn ta-btn-secondary">解封</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <h2>添加规则</h2>
                <?php $renderAdminPagination($ipBlacklistPagination ?? [], 'ip-blacklist'); ?>
                <form method="post" action="/admin/ip-blacklist/add">
                    <input type="hidden" name="csrf_token" value="<?= $csrfTokenEscaped ?>">
                    <div>
                        <label for="ip-blacklist-cidr">IP 或 CIDR</label>
                        <input id="ip-blacklist-cidr" name="ip_cidr" type="text" required placeholder="例如 203.0.113.0/24 或 2001:db8::/32"
                               >
                    </div>
                    <div>
                        <label for="ip-blacklist-reason">原因（可选）</label>
                        <input id="ip-blacklist-reason" name="reason" type="text" maxlength="255"
                               >
                    </div>
                    <button type="submit" class="ta-btn ta-btn-primary">添加</button>
                </form>
            </div>
        </div>
    </div>

</div>

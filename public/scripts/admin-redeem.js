(function () {
    'use strict';

    var root = document.getElementById('redeem-admin-root');
    if (!root) return;

    var csrfToken = String(root.getAttribute('data-csrf-token') || '');
    var state = {
        categories: [],
        batchOptions: [],
        keys: { page: 1, totalPages: 1 },
        logs: { page: 1, totalPages: 1 },
        batches: { page: 1, totalPages: 1 },
        adminLogs: { page: 1, totalPages: 1 }
    };

    function byId(id) {
        return document.getElementById(id);
    }

    function parseJsonSafe(text) {
        try { return JSON.parse(text); } catch (err) { return null; }
    }

    function pickData(payload) {
        if (!payload || typeof payload !== 'object') return {};
        if (payload.data && typeof payload.data === 'object') return payload.data;
        return payload;
    }

    function escapeHtml(raw) {
        return String(raw || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function parseCommandSnapshot(raw) {
        if (!raw) return '';
        if (Array.isArray(raw)) return raw.join('\n');
        if (typeof raw === 'string') {
            var parsed = parseJsonSafe(raw);
            if (Array.isArray(parsed)) return parsed.join('\n');
            return raw;
        }
        return '';
    }

    function parseServerIds(raw) {
        if (!raw) return [];
        if (Array.isArray(raw)) {
            return raw.map(function (x) { return String(x).trim(); }).filter(Boolean);
        }
        if (typeof raw === 'string') {
            var text = raw.trim();
            if (!text) return [];
            if (text.charAt(0) === '[') {
                var parsed = parseJsonSafe(text);
                if (Array.isArray(parsed)) {
                    return parsed.map(function (x) { return String(x).trim(); }).filter(Boolean);
                }
            }
            return text.split(/[\r\n,]+/).map(function (x) { return String(x).trim(); }).filter(Boolean);
        }
        return [];
    }

    function parseRuleSnapshot(raw) {
        if (!raw) return '';
        if (typeof raw === 'string') {
            var parsed = parseJsonSafe(raw);
            if (parsed && typeof parsed === 'object') return JSON.stringify(parsed);
            return raw;
        }
        if (typeof raw === 'object') return JSON.stringify(raw);
        return String(raw || '');
    }

    function toApiDatetime(value) {
        var v = String(value || '').trim();
        if (!v) return '';
        if (v.indexOf('T') >= 0) v = v.replace('T', ' ');
        if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test(v)) v += ':00';
        return v;
    }

    function showError(message) {
        window.alert(message || 'Operation failed');
    }

    function buildUrl(path, query) {
        var url = new URL(path, window.location.origin);
        if (query && typeof query === 'object') {
            Object.keys(query).forEach(function (key) {
                var value = query[key];
                if (value === null || value === undefined || value === '') return;
                url.searchParams.set(key, String(value));
            });
        }
        return url.pathname + url.search;
    }

    async function request(path, options) {
        options = options || {};
        var method = options.method || 'GET';
        var headers = { 'Accept': 'application/json' };
        var fetchOptions = { method: method, headers: headers, credentials: 'same-origin' };

        if (options.body !== undefined) {
            headers['Content-Type'] = 'application/json';
            if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
            fetchOptions.body = JSON.stringify(options.body);
        }

        var response = await fetch(path, fetchOptions);
        var text = await response.text();
        var payload = parseJsonSafe(text) || {};
        return { ok: response.ok, status: response.status, payload: payload };
    }

    function downloadCsv(csv, filename) {
        if (!csv) return;
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename || ('redeem_export_' + Date.now() + '.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
    }

    function updateStatsView(stats) {
        byId('redeem-stat-total').textContent = String(stats.total_keys || 0);
        byId('redeem-stat-publishable').textContent = String(stats.publishable_keys || 0);
        byId('redeem-stat-used').textContent = String(stats.used_count || 0);
        byId('redeem-stat-failed').textContent = String(stats.failed_claims || 0);
        byId('redeem-stat-revoked').textContent = String(stats.revoked_keys || 0);
        byId('redeem-stat-batch-count').textContent = String(stats.batch_count || 0);
        byId('redeem-stat-pending-failed').textContent = String(stats.pending_failed_claims || 0);
        byId('redeem-stat-today-success').textContent = String(stats.today_success_claims || 0);
        byId('redeem-stat-today-failed').textContent = String(stats.today_failed_claims || 0);
    }

    async function loadStats() {
        var res = await request('/api/admin/redeem/stats/publish');
        if (!res.ok || !res.payload.success) return;
        updateStatsView(pickData(res.payload));
    }

    function rebuildCategorySelects() {
        var keySelect = byId('redeem-keys-filter-category');
        var batchCategorySelect = byId('redeem-batch-category');
        var logSelect = byId('redeem-logs-filter-category');
        [keySelect, batchCategorySelect, logSelect].forEach(function (select, idx) {
            if (!select) return;
            var keep = select.value;
            select.innerHTML = '';
            var baseOption = document.createElement('option');
            baseOption.value = '';
            baseOption.textContent = idx === 1 ? 'Not selected' : 'All';
            select.appendChild(baseOption);
            state.categories.forEach(function (category) {
                var option = document.createElement('option');
                option.value = String(category.id);
                option.textContent = String(category.name || ('#' + category.id));
                select.appendChild(option);
            });
            if (keep) select.value = keep;
        });
    }

    function rebuildBatchOptions() {
        var select = byId('redeem-keys-filter-batch');
        if (!select) return;
        var keep = select.value;
        select.innerHTML = '<option value="">All</option>';
        state.batchOptions.forEach(function (batch) {
            var option = document.createElement('option');
            option.value = String(batch.id);
            option.textContent = String(batch.batch_no || '') + ' / ' + String(batch.name || '');
            select.appendChild(option);
        });
        if (keep) select.value = keep;
    }

    function renderCategoriesTable() {
        var tbody = document.querySelector('#redeem-categories-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (!state.categories.length) {
            var emptyTr = document.createElement('tr');
            emptyTr.innerHTML = '<td colspan="6" class="ta-help-text">No categories</td>';
            tbody.appendChild(emptyTr);
            return;
        }
        state.categories.forEach(function (category) {
            var tr = document.createElement('tr');
            tr.setAttribute('data-category-id', String(category.id));
            tr.innerHTML = ''
                + '<td>#' + Number(category.id) + '</td>'
                + '<td><input type="text" class="redeem-category-name" value="' + escapeHtml(String(category.name || '')) + '" maxlength="128"></td>'
                + '<td><select class="redeem-category-status"><option value="enabled">enabled</option><option value="disabled">disabled</option></select></td>'
                + '<td><input type="text" class="redeem-category-description" value="' + escapeHtml(String(category.description || '')) + '"></td>'
                + '<td><textarea class="redeem-category-template" rows="3">' + escapeHtml(String(category.default_command_template || '')) + '</textarea></td>'
                + '<td><div class="ta-action-stack"><button type="button" class="ta-btn ta-btn-secondary redeem-category-save">Save</button><button type="button" class="ta-btn ta-btn-secondary redeem-category-delete">Delete</button></div></td>';
            tbody.appendChild(tr);
            var statusSelect = tr.querySelector('.redeem-category-status');
            if (statusSelect) statusSelect.value = String(category.status || 'enabled');
        });
    }

    async function loadCategories() {
        var res = await request('/api/admin/redeem/categories');
        if (!res.ok || !res.payload.success) return;
        var data = pickData(res.payload);
        state.categories = Array.isArray(data.items) ? data.items : [];
        rebuildCategorySelects();
        renderCategoriesTable();
    }

    async function loadBatchOptions() {
        var res = await request(buildUrl('/api/admin/redeem/batches', { page: 1, per_page: 100 }));
        if (!res.ok || !res.payload.success) return;
        var data = pickData(res.payload);
        state.batchOptions = Array.isArray(data.items) ? data.items : [];
        rebuildBatchOptions();
    }

    function getKeyFilters() {
        return {
            status: byId('redeem-keys-filter-status').value,
            category_id: byId('redeem-keys-filter-category').value,
            batch_id: byId('redeem-keys-filter-batch').value,
            channel: byId('redeem-keys-filter-channel').value,
            bound_player_uuid: byId('redeem-keys-filter-bound-player-uuid').value,
            allowed_server_id: byId('redeem-keys-filter-allowed-server-id').value,
            require_bound_account: byId('redeem-keys-filter-require-bound-account').value,
            require_email_verified: byId('redeem-keys-filter-require-email-verified').value,
            require_account_active: byId('redeem-keys-filter-require-account-active').value,
            q: byId('redeem-keys-filter-q').value,
            created_from: toApiDatetime(byId('redeem-keys-filter-created-from').value),
            created_to: toApiDatetime(byId('redeem-keys-filter-created-to').value),
            expires_from: toApiDatetime(byId('redeem-keys-filter-expires-from').value),
            expires_to: toApiDatetime(byId('redeem-keys-filter-expires-to').value),
            per_page: byId('redeem-keys-filter-per-page').value
        };
    }

    function setKeysPagination(page, totalPages) {
        state.keys.page = page;
        state.keys.totalPages = totalPages;
        byId('redeem-keys-page-text').textContent = 'Page ' + page + ' / ' + totalPages;
    }

    function selectedKeyIds() {
        var checks = document.querySelectorAll('.redeem-key-select:checked');
        var ids = [];
        checks.forEach(function (checkbox) { ids.push(Number(checkbox.value)); });
        return ids.filter(function (id) { return id > 0; });
    }

    function renderKeysTable(items) {
        var tbody = document.querySelector('#redeem-keys-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (!items.length) {
            var emptyTr = document.createElement('tr');
            emptyTr.innerHTML = '<td colspan="18" class="ta-help-text">No keys</td>';
            tbody.appendChild(emptyTr);
            return;
        }
        items.forEach(function (item) {
            var serverIds = parseServerIds(item.allowed_server_ids);
            var limitedPlayer = [];
            if (item.bound_player_uuid) limitedPlayer.push('UUID: ' + String(item.bound_player_uuid));
            if (item.bound_player_name) limitedPlayer.push('Name: ' + String(item.bound_player_name));
            var bindingRequirements = [];
            if (Number(item.require_bound_account || 0) === 1) bindingRequirements.push('bound');
            if (Number(item.require_email_verified || 0) === 1) bindingRequirements.push('email');
            if (Number(item.require_account_active || 0) === 1) bindingRequirements.push('active');
            var tr = document.createElement('tr');
            tr.innerHTML = ''
                + '<td><input type="checkbox" class="redeem-key-select" value="' + Number(item.id) + '"></td>'
                + '<td>#' + Number(item.id) + '</td>'
                + '<td>' + escapeHtml(String(item.plain_code || '')) + '</td>'
                + '<td>' + escapeHtml(String(item.category_name || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.batch_no || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.channel || '-')) + '</td>'
                + '<td>' + escapeHtml(serverIds.length ? serverIds.join(', ') : 'all') + '</td>'
                + '<td>' + escapeHtml(limitedPlayer.length ? limitedPlayer.join(' | ') : '-') + '</td>'
                + '<td>' + escapeHtml(bindingRequirements.length ? bindingRequirements.join('+') : '-') + '</td>'
                + '<td>' + (Number(item.per_player_limit || 0) > 0 ? Number(item.per_player_limit || 0) : 'unlimited') + '</td>'
                + '<td>' + (Number(item.per_account_limit || 0) > 0 ? Number(item.per_account_limit || 0) : 'unlimited') + '</td>'
                + '<td>' + escapeHtml(String(item.rule_note || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.status || '-')) + '</td>'
                + '<td>' + Number(item.used_count || 0) + ' / ' + Number(item.max_uses || 0) + '</td>'
                + '<td>' + escapeHtml(String(item.created_at || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.expires_at || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.remark || '-')) + '</td>'
                + '<td><button type="button" class="ta-btn ta-btn-secondary redeem-key-revoke" data-id="' + Number(item.id) + '">Revoke</button></td>';
            tbody.appendChild(tr);
        });
    }

    async function loadKeys(page) {
        page = page || 1;
        var filters = getKeyFilters();
        var res = await request(buildUrl('/api/admin/redeem/keys', {
            page: page,
            per_page: filters.per_page,
            status: filters.status,
            category_id: filters.category_id,
            batch_id: filters.batch_id,
            channel: filters.channel,
            bound_player_uuid: filters.bound_player_uuid,
            allowed_server_id: filters.allowed_server_id,
            require_bound_account: filters.require_bound_account,
            require_email_verified: filters.require_email_verified,
            require_account_active: filters.require_account_active,
            q: filters.q,
            created_from: filters.created_from,
            created_to: filters.created_to,
            expires_from: filters.expires_from,
            expires_to: filters.expires_to
        }));
        if (!res.ok || !res.payload.success) {
            showError(res.payload.message || 'Failed to load keys');
            return;
        }
        var data = pickData(res.payload);
        renderKeysTable(Array.isArray(data.items) ? data.items : []);
        setKeysPagination(Number(data.page || 1), Number(data.total_pages || 1));
        var selectAll = byId('redeem-keys-select-all');
        if (selectAll) selectAll.checked = false;
    }

    function getLogFilters() {
        return {
            status: byId('redeem-logs-filter-status').value,
            admin_status: byId('redeem-logs-filter-admin-status').value,
            category_id: byId('redeem-logs-filter-category').value,
            rule_result: byId('redeem-logs-filter-rule-result').value,
            rule_reason: byId('redeem-logs-filter-rule-reason').value,
            website_user_id: byId('redeem-logs-filter-website-user-id').value,
            server_id: byId('redeem-logs-filter-server-id').value,
            player_uuid: byId('redeem-logs-filter-player-uuid').value,
            player_name: byId('redeem-logs-filter-player-name').value,
            q: byId('redeem-logs-filter-q').value,
            created_from: toApiDatetime(byId('redeem-logs-filter-created-from').value),
            created_to: toApiDatetime(byId('redeem-logs-filter-created-to').value),
            per_page: byId('redeem-logs-filter-per-page').value
        };
    }

    function setLogsPagination(page, totalPages) {
        state.logs.page = page;
        state.logs.totalPages = totalPages;
        byId('redeem-logs-page-text').textContent = 'Page ' + page + ' / ' + totalPages;
    }

    function renderLogsTable(items) {
        var tbody = document.querySelector('#redeem-logs-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (!items.length) {
            var emptyTr = document.createElement('tr');
            emptyTr.innerHTML = '<td colspan="17" class="ta-help-text">No logs</td>';
            tbody.appendChild(emptyTr);
            return;
        }

        items.forEach(function (item) {
            var commandSnapshot = parseCommandSnapshot(item.command_snapshot);
            var executedCommands = parseCommandSnapshot(item.executed_commands);
            var ruleSnapshot = parseRuleSnapshot(item.rule_snapshot_json);
            var combined = commandSnapshot;
            if (executedCommands) combined += (combined ? '\n---\n' : '') + executedCommands;

            var isPendingFailed = String(item.status || '') === 'failed' && String(item.admin_status || '') === 'pending';
            var adminNote = String(item.admin_note || '');
            var actionHtml = '-';
            if (String(item.status || '') === 'failed') {
                actionHtml = ''
                    + '<div class="ta-action-stack">'
                    + '<input type="text" class="redeem-log-admin-note" data-id="' + Number(item.id) + '" value="' + escapeHtml(adminNote) + '" placeholder="admin note" maxlength="500">'
                    + '<button type="button" class="ta-btn ta-btn-secondary redeem-log-mark" data-id="' + Number(item.id) + '" data-status="handled">handled</button>'
                    + '<button type="button" class="ta-btn ta-btn-secondary redeem-log-mark" data-id="' + Number(item.id) + '" data-status="ignored">ignored</button>'
                    + '<button type="button" class="ta-btn ta-btn-secondary redeem-log-mark" data-id="' + Number(item.id) + '" data-status="pending">pending</button>'
                    + '</div>';
            }

            var tr = document.createElement('tr');
            tr.setAttribute('data-log-id', String(Number(item.id || 0)));
            tr.style.background = isPendingFailed ? 'rgba(239, 68, 68, 0.08)' : '';
            tr.innerHTML = ''
                + '<td>#' + Number(item.id) + '</td>'
                + '<td>' + escapeHtml(String(item.created_at || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.player_name || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.player_uuid || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.server_id || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.world_name || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.status || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.admin_status || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.rule_result || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.rule_reason || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.website_user_id || '-')) + '</td>'
                + '<td>' + escapeHtml(String((item.batch_no || '-') + ' / ' + (item.channel || '-'))) + '</td>'
                + '<td>' + escapeHtml(String(item.failure_reason || '-')) + '</td>'
                + '<td>' + escapeHtml(adminNote || '-') + '</td>'
                + '<td><textarea rows="4" readonly>' + escapeHtml(ruleSnapshot || '-') + '</textarea></td>'
                + '<td><textarea rows="4" readonly>' + escapeHtml(combined || '-') + '</textarea></td>'
                + '<td>' + actionHtml + '</td>';
            tbody.appendChild(tr);
        });
    }

    async function loadLogs(page) {
        page = page || 1;
        var filters = getLogFilters();
        var res = await request(buildUrl('/api/admin/redeem/logs', {
            page: page,
            per_page: filters.per_page,
            status: filters.status,
            admin_status: filters.admin_status,
            category_id: filters.category_id,
            rule_result: filters.rule_result,
            rule_reason: filters.rule_reason,
            website_user_id: filters.website_user_id,
            server_id: filters.server_id,
            player_uuid: filters.player_uuid,
            player_name: filters.player_name,
            q: filters.q,
            created_from: filters.created_from,
            created_to: filters.created_to
        }));
        if (!res.ok || !res.payload.success) {
            showError(res.payload.message || 'Failed to load logs');
            return;
        }
        var data = pickData(res.payload);
        renderLogsTable(Array.isArray(data.items) ? data.items : []);
        setLogsPagination(Number(data.page || 1), Number(data.total_pages || 1));
    }

    function getBatchFilters() {
        return {
            channel: byId('redeem-batches-filter-channel').value,
            q: byId('redeem-batches-filter-q').value,
            created_from: toApiDatetime(byId('redeem-batches-filter-created-from').value),
            per_page: byId('redeem-batches-filter-per-page').value
        };
    }

    function setBatchesPagination(page, totalPages) {
        state.batches.page = page;
        state.batches.totalPages = totalPages;
        byId('redeem-batches-page-text').textContent = 'Page ' + page + ' / ' + totalPages;
    }

    function renderBatchesTable(items) {
        var tbody = document.querySelector('#redeem-batches-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (!items.length) {
            var emptyTr = document.createElement('tr');
            emptyTr.innerHTML = '<td colspan="8" class="ta-help-text">No batches</td>';
            tbody.appendChild(emptyTr);
            return;
        }
        items.forEach(function (item) {
            var tr = document.createElement('tr');
            tr.innerHTML = ''
                + '<td>#' + Number(item.id) + '</td>'
                + '<td>' + escapeHtml(String(item.batch_no || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.name || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.channel || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.category_name || '-')) + '</td>'
                + '<td>' + Number(item.total_count || 0) + '</td>'
                + '<td>' + escapeHtml(String(item.created_at || '-')) + '</td>'
                + '<td><button type="button" class="ta-btn ta-btn-secondary redeem-batch-view-stats" data-id="' + Number(item.id) + '">Stats</button></td>';
            tbody.appendChild(tr);
        });
    }

    async function loadBatches(page) {
        page = page || 1;
        var filters = getBatchFilters();
        var res = await request(buildUrl('/api/admin/redeem/batches', {
            page: page,
            per_page: filters.per_page,
            channel: filters.channel,
            q: filters.q,
            created_from: filters.created_from
        }));
        if (!res.ok || !res.payload.success) {
            showError(res.payload.message || 'Failed to load batches');
            return;
        }
        var data = pickData(res.payload);
        renderBatchesTable(Array.isArray(data.items) ? data.items : []);
        setBatchesPagination(Number(data.page || 1), Number(data.total_pages || 1));
    }

    function getAdminLogFilters() {
        return {
            action: byId('redeem-admin-logs-filter-action').value,
            target_type: byId('redeem-admin-logs-filter-target-type').value,
            q: byId('redeem-admin-logs-filter-q').value,
            created_from: toApiDatetime(byId('redeem-admin-logs-filter-created-from').value),
            created_to: toApiDatetime(byId('redeem-admin-logs-filter-created-to').value),
            per_page: byId('redeem-admin-logs-filter-per-page').value
        };
    }

    function setAdminLogsPagination(page, totalPages) {
        state.adminLogs.page = page;
        state.adminLogs.totalPages = totalPages;
        byId('redeem-admin-logs-page-text').textContent = 'Page ' + page + ' / ' + totalPages;
    }

    function renderAdminLogsTable(items) {
        var tbody = document.querySelector('#redeem-admin-logs-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (!items.length) {
            var emptyTr = document.createElement('tr');
            emptyTr.innerHTML = '<td colspan="6" class="ta-help-text">No admin logs</td>';
            tbody.appendChild(emptyTr);
            return;
        }
        items.forEach(function (item) {
            var detail = item.detail;
            if (!detail && item.detail_json) detail = parseJsonSafe(String(item.detail_json || ''));
            var detailText = detail ? JSON.stringify(detail) : '-';
            var tr = document.createElement('tr');
            tr.innerHTML = ''
                + '<td>' + escapeHtml(String(item.created_at || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.admin_id || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.action || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.target_type || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.target_id || '-')) + '</td>'
                + '<td><textarea rows="3" readonly>' + escapeHtml(detailText) + '</textarea></td>';
            tbody.appendChild(tr);
        });
    }

    async function loadAdminLogs(page) {
        page = page || 1;
        var filters = getAdminLogFilters();
        var res = await request(buildUrl('/api/admin/redeem/admin-logs', {
            page: page,
            per_page: filters.per_page,
            action: filters.action,
            target_type: filters.target_type,
            q: filters.q,
            created_from: filters.created_from,
            created_to: filters.created_to
        }));
        if (!res.ok || !res.payload.success) {
            showError(res.payload.message || 'Failed to load admin logs');
            return;
        }
        var data = pickData(res.payload);
        renderAdminLogsTable(Array.isArray(data.items) ? data.items : []);
        setAdminLogsPagination(Number(data.page || 1), Number(data.total_pages || 1));
    }

    async function doBatchAction(path, ids, successText) {
        if (!ids.length) {
            showError('Please select keys first');
            return;
        }
        var res = await request(path, { method: 'POST', body: { ids: ids } });
        if (!res.ok || !res.payload.success) {
            showError(res.payload.message || 'Batch action failed');
            return;
        }
        window.alert(successText + ', affected: ' + Number(res.payload.affected || 0));
        await Promise.all([loadKeys(state.keys.page), loadStats(), loadAdminLogs(1)]);
    }

    async function markLogAdminStatus(logId, status) {
        var row = document.querySelector('tr[data-log-id="' + logId + '"]');
        var noteInput = row ? row.querySelector('.redeem-log-admin-note[data-id="' + logId + '"]') : null;
        var adminNote = noteInput ? String(noteInput.value || '').trim() : '';
        var res = await request('/api/admin/redeem/logs/' + logId + '/admin-status', {
            method: 'PATCH',
            body: { adminStatus: status, adminNote: adminNote }
        });
        if (!res.ok || !res.payload.success) {
            showError(res.payload.message || 'Failed to update admin status');
            return;
        }
        await Promise.all([loadLogs(state.logs.page), loadStats(), loadAdminLogs(1)]);
    }

    async function exportCurrentKeyFilters() {
        var filters = getKeyFilters();
        var res = await request(buildUrl('/api/admin/redeem/keys/export', {
            status: filters.status,
            category_id: filters.category_id,
            batch_id: filters.batch_id,
            channel: filters.channel,
            bound_player_uuid: filters.bound_player_uuid,
            allowed_server_id: filters.allowed_server_id,
            require_bound_account: filters.require_bound_account,
            require_email_verified: filters.require_email_verified,
            require_account_active: filters.require_account_active,
            q: filters.q,
            created_from: filters.created_from,
            created_to: filters.created_to,
            expires_from: filters.expires_from,
            expires_to: filters.expires_to
        }));
        if (!res.ok || !res.payload.success) {
            showError(res.payload.message || 'Export failed');
            return;
        }
        var data = pickData(res.payload);
        var csv = String(data.csv || res.payload.csv || '');
        var filename = String(data.filename || res.payload.filename || ('redeem_keys_export_' + Date.now() + '.csv'));
        downloadCsv(csv, filename);
        await loadAdminLogs(1);
    }

    async function viewBatchStats(batchId) {
        var res = await request('/api/admin/redeem/batches/' + batchId + '/stats');
        if (!res.ok || !res.payload.success) {
            showError(res.payload.message || 'Failed to load batch stats');
            return;
        }
        var data = pickData(res.payload);
        var batch = data.batch || {};
        var stats = data.stats || {};
        var text = [
            'Batch: ' + String(batch.batch_no || ''),
            'Name: ' + String(batch.name || ''),
            'Channel: ' + String(batch.channel || ''),
            'Total keys: ' + Number(stats.total_keys || 0),
            'Publishable: ' + Number(stats.publishable_keys || 0),
            'Revoked: ' + Number(stats.revoked_keys || 0),
            'Deleted: ' + Number(stats.deleted_keys || 0),
            'Success logs: ' + Number(stats.success_logs || 0),
            'Failed logs: ' + Number(stats.failed_logs || 0),
            'Pending failed: ' + Number(stats.pending_failed_logs || 0)
        ].join('\n');
        window.alert(text);
    }

    async function bindEvents() {
        byId('redeem-keys-refresh-btn').addEventListener('click', function () { loadKeys(1); });
        byId('redeem-keys-filter-status').addEventListener('change', function () { loadKeys(1); });
        byId('redeem-keys-filter-category').addEventListener('change', function () { loadKeys(1); });
        byId('redeem-keys-filter-batch').addEventListener('change', function () { loadKeys(1); });
        byId('redeem-keys-filter-require-bound-account').addEventListener('change', function () { loadKeys(1); });
        byId('redeem-keys-filter-require-email-verified').addEventListener('change', function () { loadKeys(1); });
        byId('redeem-keys-filter-require-account-active').addEventListener('change', function () { loadKeys(1); });
        byId('redeem-keys-filter-per-page').addEventListener('change', function () { loadKeys(1); });
        byId('redeem-keys-filter-created-from').addEventListener('change', function () { loadKeys(1); });
        byId('redeem-keys-filter-created-to').addEventListener('change', function () { loadKeys(1); });
        byId('redeem-keys-filter-expires-from').addEventListener('change', function () { loadKeys(1); });
        byId('redeem-keys-filter-expires-to').addEventListener('change', function () { loadKeys(1); });
        ['redeem-keys-filter-channel', 'redeem-keys-filter-bound-player-uuid', 'redeem-keys-filter-allowed-server-id', 'redeem-keys-filter-q'].forEach(function (id) {
            byId(id).addEventListener('keydown', function (event) {
                if (event.key === 'Enter') { event.preventDefault(); loadKeys(1); }
            });
        });
        byId('redeem-keys-prev').addEventListener('click', function () {
            if (state.keys.page > 1) loadKeys(state.keys.page - 1);
        });
        byId('redeem-keys-next').addEventListener('click', function () {
            if (state.keys.page < state.keys.totalPages) loadKeys(state.keys.page + 1);
        });
        byId('redeem-keys-select-all').addEventListener('change', function (event) {
            var checked = !!event.target.checked;
            document.querySelectorAll('.redeem-key-select').forEach(function (checkbox) {
                checkbox.checked = checked;
            });
        });
        byId('redeem-keys-table').addEventListener('click', async function (event) {
            var btn = event.target.closest('.redeem-key-revoke');
            if (!btn) return;
            var id = Number(btn.getAttribute('data-id'));
            if (!id) return;
            var res = await request('/api/admin/redeem/keys/' + id + '/revoke', { method: 'PATCH', body: {} });
            if (!res.ok || !res.payload.success) {
                showError(res.payload.message || 'Failed to revoke key');
                return;
            }
            await Promise.all([loadKeys(state.keys.page), loadStats(), loadAdminLogs(1)]);
        });
        byId('redeem-keys-revoke-batch-btn').addEventListener('click', function () {
            doBatchAction('/api/admin/redeem/keys/revoke-batch', selectedKeyIds(), 'Batch revoke completed');
        });
        byId('redeem-keys-delete-batch-btn').addEventListener('click', function () {
            if (!window.confirm('Soft delete selected keys?')) return;
            doBatchAction('/api/admin/redeem/keys/delete-batch', selectedKeyIds(), 'Batch soft delete completed');
        });
        byId('redeem-keys-export-btn').addEventListener('click', exportCurrentKeyFilters);

        var headerGenerateJumpBtn = byId('redeem-header-generate-jump-btn');
        if (headerGenerateJumpBtn) {
            headerGenerateJumpBtn.addEventListener('click', function () {
                var form = byId('redeem-batch-form');
                if (!form) return;

                var target = form.closest('.redeem-config-card') || form;
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });

                var categorySelect = byId('redeem-batch-category');
                if (categorySelect) {
                    setTimeout(function () {
                        categorySelect.focus({ preventScroll: true });
                    }, 350);
                }
            });
        }

        byId('redeem-batch-form').addEventListener('submit', async function (event) {
            event.preventDefault();
            var formData = new FormData(event.target);
            var payload = {
                categoryId: formData.get('categoryId') || null,
                count: Number(formData.get('count') || 0),
                length: Number(formData.get('length') || 16),
                maxUses: Number(formData.get('maxUses') || 1),
                expiresAt: String(formData.get('expiresAt') || '').trim(),
                batchName: String(formData.get('batchName') || '').trim(),
                channel: String(formData.get('channel') || '').trim(),
                remark: String(formData.get('remark') || '').trim(),
                allowedServerIds: String(formData.get('allowedServerIds') || '').trim(),
                boundPlayerUuid: String(formData.get('boundPlayerUuid') || '').trim(),
                boundPlayerName: String(formData.get('boundPlayerName') || '').trim(),
                requireBoundAccount: formData.get('requireBoundAccount') ? 1 : 0,
                requireEmailVerified: formData.get('requireEmailVerified') ? 1 : 0,
                requireAccountActive: formData.get('requireAccountActive') ? 1 : 0,
                perPlayerLimit: Number(formData.get('perPlayerLimit') || 0),
                perAccountLimit: Number(formData.get('perAccountLimit') || 0),
                ruleNote: String(formData.get('ruleNote') || '').trim(),
                commandTemplate: String(formData.get('commandTemplate') || '').trim()
            };
            if (payload.categoryId === '') payload.categoryId = null;
            var res = await request('/api/admin/redeem/keys/batch', { method: 'POST', body: payload });
            if (!res.ok || !res.payload.success) {
                showError(res.payload.message || 'Batch generate failed');
                return;
            }
            var data = pickData(res.payload);
            var csv = String(data.csv || res.payload.csv || '');
            var filename = String(data.filename || res.payload.filename || ('redeem_keys_' + Date.now() + '.csv'));
            downloadCsv(csv, filename);
            var createdItems = data.items || res.payload.items || [];
            var batch = data.batch || res.payload.batch || {};
            byId('redeem-batch-result').textContent = 'Created: ' + (Array.isArray(createdItems) ? createdItems.length : 0) + ', batch: ' + String(batch.batchNo || '-');
            await Promise.all([loadKeys(1), loadStats(), loadBatches(1), loadBatchOptions(), loadAdminLogs(1)]);
        });

        byId('redeem-batches-refresh-btn').addEventListener('click', function () { loadBatches(1); });
        byId('redeem-batches-filter-per-page').addEventListener('change', function () { loadBatches(1); });
        byId('redeem-batches-filter-created-from').addEventListener('change', function () { loadBatches(1); });
        ['redeem-batches-filter-channel', 'redeem-batches-filter-q'].forEach(function (id) {
            byId(id).addEventListener('keydown', function (event) {
                if (event.key === 'Enter') { event.preventDefault(); loadBatches(1); }
            });
        });
        byId('redeem-batches-prev').addEventListener('click', function () {
            if (state.batches.page > 1) loadBatches(state.batches.page - 1);
        });
        byId('redeem-batches-next').addEventListener('click', function () {
            if (state.batches.page < state.batches.totalPages) loadBatches(state.batches.page + 1);
        });
        byId('redeem-batches-table').addEventListener('click', function (event) {
            var btn = event.target.closest('.redeem-batch-view-stats');
            if (!btn) return;
            var id = Number(btn.getAttribute('data-id'));
            if (!id) return;
            viewBatchStats(id);
        });

        byId('redeem-category-create-form').addEventListener('submit', async function (event) {
            event.preventDefault();
            var formData = new FormData(event.target);
            var payload = {
                name: String(formData.get('name') || '').trim(),
                status: String(formData.get('status') || 'enabled').trim(),
                description: String(formData.get('description') || '').trim(),
                defaultCommandTemplate: String(formData.get('defaultCommandTemplate') || '').trim()
            };
            var res = await request('/api/admin/redeem/categories', { method: 'POST', body: payload });
            if (!res.ok || !res.payload.success) {
                showError(res.payload.message || 'Failed to create category');
                return;
            }
            event.target.reset();
            await Promise.all([loadCategories(), loadAdminLogs(1)]);
        });

        byId('redeem-categories-table').addEventListener('click', async function (event) {
            var row = event.target.closest('tr[data-category-id]');
            if (!row) return;
            var id = Number(row.getAttribute('data-category-id'));
            if (!id) return;
            if (event.target.closest('.redeem-category-save')) {
                var payload = {
                    name: String(row.querySelector('.redeem-category-name').value || '').trim(),
                    status: String(row.querySelector('.redeem-category-status').value || 'enabled').trim(),
                    description: String(row.querySelector('.redeem-category-description').value || '').trim(),
                    defaultCommandTemplate: String(row.querySelector('.redeem-category-template').value || '').trim()
                };
                var updateRes = await request('/api/admin/redeem/categories/' + id, { method: 'PATCH', body: payload });
                if (!updateRes.ok || !updateRes.payload.success) {
                    showError(updateRes.payload.message || 'Failed to update category');
                    return;
                }
                await Promise.all([loadCategories(), loadAdminLogs(1)]);
                return;
            }
            if (event.target.closest('.redeem-category-delete')) {
                if (!window.confirm('Delete this category?')) return;
                var deleteRes = await request('/api/admin/redeem/categories/' + id, { method: 'DELETE', body: {} });
                if (!deleteRes.ok || !deleteRes.payload.success) {
                    showError(deleteRes.payload.message || 'Failed to delete category');
                    return;
                }
                await Promise.all([loadCategories(), loadKeys(state.keys.page), loadLogs(state.logs.page), loadAdminLogs(1)]);
            }
        });

        byId('redeem-logs-refresh-btn').addEventListener('click', function () { loadLogs(1); });
        ['redeem-logs-filter-status', 'redeem-logs-filter-admin-status', 'redeem-logs-filter-category', 'redeem-logs-filter-rule-result', 'redeem-logs-filter-per-page', 'redeem-logs-filter-created-from', 'redeem-logs-filter-created-to']
            .forEach(function (id) { byId(id).addEventListener('change', function () { loadLogs(1); }); });
        ['redeem-logs-filter-server-id', 'redeem-logs-filter-player-uuid', 'redeem-logs-filter-player-name', 'redeem-logs-filter-rule-reason', 'redeem-logs-filter-website-user-id', 'redeem-logs-filter-q']
            .forEach(function (id) {
                byId(id).addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') { event.preventDefault(); loadLogs(1); }
                });
            });
        byId('redeem-logs-prev').addEventListener('click', function () {
            if (state.logs.page > 1) loadLogs(state.logs.page - 1);
        });
        byId('redeem-logs-next').addEventListener('click', function () {
            if (state.logs.page < state.logs.totalPages) loadLogs(state.logs.page + 1);
        });
        byId('redeem-logs-table').addEventListener('click', function (event) {
            var btn = event.target.closest('.redeem-log-mark');
            if (!btn) return;
            var id = Number(btn.getAttribute('data-id'));
            var status = String(btn.getAttribute('data-status') || '');
            if (!id || !status) return;
            markLogAdminStatus(id, status);
        });

        byId('redeem-admin-logs-refresh-btn').addEventListener('click', function () { loadAdminLogs(1); });
        ['redeem-admin-logs-filter-per-page', 'redeem-admin-logs-filter-created-from', 'redeem-admin-logs-filter-created-to']
            .forEach(function (id) { byId(id).addEventListener('change', function () { loadAdminLogs(1); }); });
        ['redeem-admin-logs-filter-action', 'redeem-admin-logs-filter-target-type', 'redeem-admin-logs-filter-q']
            .forEach(function (id) {
                byId(id).addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') { event.preventDefault(); loadAdminLogs(1); }
                });
            });
        byId('redeem-admin-logs-prev').addEventListener('click', function () {
            if (state.adminLogs.page > 1) loadAdminLogs(state.adminLogs.page - 1);
        });
        byId('redeem-admin-logs-next').addEventListener('click', function () {
            if (state.adminLogs.page < state.adminLogs.totalPages) loadAdminLogs(state.adminLogs.page + 1);
        });
    }

    async function init() {
        await bindEvents();
        await loadCategories();
        await loadBatchOptions();
        await Promise.all([loadStats(), loadKeys(1), loadLogs(1), loadBatches(1), loadAdminLogs(1)]);
    }

    init();
})();

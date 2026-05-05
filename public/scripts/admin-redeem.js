(function () {
    'use strict';

    var root = document.getElementById('redeem-admin-root');
    if (!root) {
        return;
    }

    var csrfToken = String(root.getAttribute('data-csrf-token') || '');

    var state = {
        categories: [],
        keys: {
            page: 1,
            totalPages: 1,
        },
        logs: {
            page: 1,
            totalPages: 1,
        }
    };

    function parseJsonSafe(text) {
        try {
            return JSON.parse(text);
        } catch (err) {
            return null;
        }
    }

    function parseCommandSnapshot(raw) {
        if (!raw) return '';
        if (Array.isArray(raw)) return raw.join('\n');
        if (typeof raw === 'string') {
            var parsed = parseJsonSafe(raw);
            if (Array.isArray(parsed)) {
                return parsed.join('\n');
            }
            return raw;
        }
        return '';
    }

    function pickData(payload) {
        if (!payload || typeof payload !== 'object') {
            return {};
        }
        if (payload.data && typeof payload.data === 'object') {
            return payload.data;
        }
        return payload;
    }

    function showError(message) {
        window.alert(message || '操作失败');
    }

    function buildUrl(path, query) {
        var url = new URL(path, window.location.origin);
        if (query && typeof query === 'object') {
            Object.keys(query).forEach(function (key) {
                var value = query[key];
                if (value === null || value === undefined || value === '') {
                    return;
                }
                url.searchParams.set(key, String(value));
            });
        }
        return url.pathname + url.search;
    }

    async function request(path, options) {
        options = options || {};
        var method = options.method || 'GET';
        var body = options.body;
        var headers = {
            'Accept': 'application/json'
        };

        var fetchOptions = {
            method: method,
            headers: headers,
            credentials: 'same-origin'
        };

        if (body !== undefined) {
            headers['Content-Type'] = 'application/json';
            if (csrfToken) {
                headers['X-CSRF-Token'] = csrfToken;
            }
            fetchOptions.body = JSON.stringify(body);
        }

        var response = await fetch(path, fetchOptions);
        var text = await response.text();
        var payload = parseJsonSafe(text) || {};

        return {
            ok: response.ok,
            status: response.status,
            payload: payload
        };
    }

    function updateStatsView(stats) {
        document.getElementById('redeem-stat-total').textContent = String(stats.total_keys || 0);
        document.getElementById('redeem-stat-publishable').textContent = String(stats.publishable_keys || 0);
        document.getElementById('redeem-stat-used').textContent = String(stats.used_count || 0);
        document.getElementById('redeem-stat-failed').textContent = String(stats.failed_claims || 0);
        document.getElementById('redeem-stat-revoked').textContent = String(stats.revoked_keys || 0);
    }

    async function loadStats() {
        var res = await request('/api/admin/redeem/stats/publish');
        if (!res.ok || !res.payload.success) {
            return;
        }
        var data = pickData(res.payload);
        updateStatsView(data);
    }

    function rebuildCategorySelects() {
        var keySelect = document.getElementById('redeem-keys-filter-category');
        var batchSelect = document.getElementById('redeem-batch-category');
        var logsSelect = document.getElementById('redeem-logs-filter-category');

        [keySelect, batchSelect, logsSelect].forEach(function (select, idx) {
            if (!select) return;
            var keep = select.value;
            select.innerHTML = '';

            var baseOption = document.createElement('option');
            baseOption.value = '';
            baseOption.textContent = idx === 1 ? '不选择分类' : '全部';
            select.appendChild(baseOption);

            state.categories.forEach(function (category) {
                var option = document.createElement('option');
                option.value = String(category.id);
                option.textContent = String(category.name || ('#' + category.id));
                select.appendChild(option);
            });

            if (keep) {
                select.value = keep;
            }
        });
    }

    function renderCategoriesTable() {
        var tbody = document.querySelector('#redeem-categories-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!state.categories.length) {
            var emptyTr = document.createElement('tr');
            emptyTr.innerHTML = '<td colspan="6" class="ta-help-text">暂无分类</td>';
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
                + '<td>'
                + '  <div class="ta-action-stack">'
                + '      <button type="button" class="ta-btn ta-btn-secondary redeem-category-save">保存</button>'
                + '      <button type="button" class="ta-btn ta-btn-secondary redeem-category-delete">删除</button>'
                + '  </div>'
                + '</td>';
            tbody.appendChild(tr);

            var statusSelect = tr.querySelector('.redeem-category-status');
            if (statusSelect) {
                statusSelect.value = String(category.status || 'enabled');
            }
        });
    }

    function escapeHtml(raw) {
        return raw
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    async function loadCategories() {
        var res = await request('/api/admin/redeem/categories');
        if (!res.ok || !res.payload.success) {
            return;
        }

        var data = pickData(res.payload);
        state.categories = Array.isArray(data.items) ? data.items : [];
        rebuildCategorySelects();
        renderCategoriesTable();
    }

    function getKeyFilters() {
        return {
            status: document.getElementById('redeem-keys-filter-status').value,
            category_id: document.getElementById('redeem-keys-filter-category').value,
            q: document.getElementById('redeem-keys-filter-q').value,
            per_page: document.getElementById('redeem-keys-filter-per-page').value
        };
    }

    function setKeysPagination(page, totalPages) {
        state.keys.page = page;
        state.keys.totalPages = totalPages;
        document.getElementById('redeem-keys-page-text').textContent = 'Page ' + page + ' / ' + totalPages;
    }

    function selectedKeyIds() {
        var checks = document.querySelectorAll('.redeem-key-select:checked');
        var ids = [];
        checks.forEach(function (checkbox) {
            ids.push(Number(checkbox.value));
        });
        return ids.filter(function (id) { return id > 0; });
    }

    function renderKeysTable(items) {
        var tbody = document.querySelector('#redeem-keys-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!items.length) {
            var emptyTr = document.createElement('tr');
            emptyTr.innerHTML = '<td colspan="9" class="ta-help-text">暂无卡密</td>';
            tbody.appendChild(emptyTr);
            return;
        }

        items.forEach(function (item) {
            var tr = document.createElement('tr');
            tr.innerHTML = ''
                + '<td><input type="checkbox" class="redeem-key-select" value="' + Number(item.id) + '"></td>'
                + '<td>#' + Number(item.id) + '</td>'
                + '<td>' + escapeHtml(String(item.plain_code || '')) + '</td>'
                + '<td>' + escapeHtml(String(item.category_name || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.status || '-')) + '</td>'
                + '<td>' + Number(item.used_count || 0) + ' / ' + Number(item.max_uses || 0) + '</td>'
                + '<td>' + escapeHtml(String(item.expires_at || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.remark || '-')) + '</td>'
                + '<td><button type="button" class="ta-btn ta-btn-secondary redeem-key-revoke" data-id="' + Number(item.id) + '">吊销</button></td>';
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
            q: filters.q
        }));

        if (!res.ok || !res.payload.success) {
            showError(res.payload.message || '卡密列表读取失败');
            return;
        }

        var data = pickData(res.payload);
        var items = Array.isArray(data.items) ? data.items : [];
        renderKeysTable(items);
        setKeysPagination(Number(data.page || 1), Number(data.total_pages || 1));

        var selectAll = document.getElementById('redeem-keys-select-all');
        if (selectAll) {
            selectAll.checked = false;
        }
    }

    function setLogsPagination(page, totalPages) {
        state.logs.page = page;
        state.logs.totalPages = totalPages;
        document.getElementById('redeem-logs-page-text').textContent = 'Page ' + page + ' / ' + totalPages;
    }

    function renderLogsTable(items) {
        var tbody = document.querySelector('#redeem-logs-table tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!items.length) {
            var emptyTr = document.createElement('tr');
            emptyTr.innerHTML = '<td colspan="9" class="ta-help-text">暂无日志</td>';
            tbody.appendChild(emptyTr);
            return;
        }

        items.forEach(function (item) {
            var commandSnapshot = parseCommandSnapshot(item.command_snapshot);
            var executedCommands = parseCommandSnapshot(item.executed_commands);
            var combined = commandSnapshot;
            if (executedCommands) {
                combined += (combined ? '\n---\n' : '') + executedCommands;
            }

            var tr = document.createElement('tr');
            tr.innerHTML = ''
                + '<td>#' + Number(item.id) + '</td>'
                + '<td>' + escapeHtml(String(item.created_at || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.player_name || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.player_uuid || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.server_id || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.world_name || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.status || '-')) + '</td>'
                + '<td>' + escapeHtml(String(item.failure_reason || '-')) + '</td>'
                + '<td><textarea rows="4" readonly>' + escapeHtml(combined || '-') + '</textarea></td>';
            tbody.appendChild(tr);
        });
    }

    function getLogFilters() {
        return {
            status: document.getElementById('redeem-logs-filter-status').value,
            category_id: document.getElementById('redeem-logs-filter-category').value,
            q: document.getElementById('redeem-logs-filter-q').value,
            per_page: document.getElementById('redeem-logs-filter-per-page').value
        };
    }

    async function loadLogs(page) {
        page = page || 1;
        var filters = getLogFilters();

        var res = await request(buildUrl('/api/admin/redeem/logs', {
            page: page,
            per_page: filters.per_page,
            status: filters.status,
            category_id: filters.category_id,
            q: filters.q
        }));

        if (!res.ok || !res.payload.success) {
            showError(res.payload.message || '兑换日志读取失败');
            return;
        }

        var data = pickData(res.payload);
        renderLogsTable(Array.isArray(data.items) ? data.items : []);
        setLogsPagination(Number(data.page || 1), Number(data.total_pages || 1));
    }

    async function doBatchAction(path, ids, successText) {
        if (!ids.length) {
            showError('请先勾选卡密');
            return;
        }

        var res = await request(path, {
            method: 'POST',
            body: { ids: ids }
        });

        if (!res.ok || !res.payload.success) {
            showError(res.payload.message || '批量操作失败');
            return;
        }

        window.alert(successText + '，影响记录：' + Number(res.payload.affected || 0));
        await Promise.all([loadKeys(state.keys.page), loadStats()]);
    }

    async function bindEvents() {
        document.getElementById('redeem-keys-refresh-btn').addEventListener('click', function () {
            loadKeys(1);
        });

        document.getElementById('redeem-keys-filter-status').addEventListener('change', function () { loadKeys(1); });
        document.getElementById('redeem-keys-filter-category').addEventListener('change', function () { loadKeys(1); });
        document.getElementById('redeem-keys-filter-per-page').addEventListener('change', function () { loadKeys(1); });
        document.getElementById('redeem-keys-filter-q').addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                loadKeys(1);
            }
        });

        document.getElementById('redeem-keys-prev').addEventListener('click', function () {
            if (state.keys.page > 1) {
                loadKeys(state.keys.page - 1);
            }
        });
        document.getElementById('redeem-keys-next').addEventListener('click', function () {
            if (state.keys.page < state.keys.totalPages) {
                loadKeys(state.keys.page + 1);
            }
        });

        document.getElementById('redeem-keys-select-all').addEventListener('change', function (event) {
            var checked = !!event.target.checked;
            document.querySelectorAll('.redeem-key-select').forEach(function (checkbox) {
                checkbox.checked = checked;
            });
        });

        document.getElementById('redeem-keys-table').addEventListener('click', async function (event) {
            var target = event.target;
            var revokeBtn = target.closest('.redeem-key-revoke');
            if (!revokeBtn) return;

            var id = Number(revokeBtn.getAttribute('data-id'));
            if (!id) return;

            var res = await request('/api/admin/redeem/keys/' + id + '/revoke', {
                method: 'PATCH',
                body: {}
            });
            if (!res.ok || !res.payload.success) {
                showError(res.payload.message || '吊销失败');
                return;
            }

            await Promise.all([loadKeys(state.keys.page), loadStats()]);
        });

        document.getElementById('redeem-keys-revoke-batch-btn').addEventListener('click', function () {
            doBatchAction('/api/admin/redeem/keys/revoke-batch', selectedKeyIds(), '批量吊销完成');
        });

        document.getElementById('redeem-keys-delete-batch-btn').addEventListener('click', function () {
            doBatchAction('/api/admin/redeem/keys/delete-batch', selectedKeyIds(), '批量删除完成');
        });

        document.getElementById('redeem-batch-form').addEventListener('submit', async function (event) {
            event.preventDefault();
            var form = event.target;
            var formData = new FormData(form);
            var payload = {
                categoryId: formData.get('categoryId') || null,
                count: Number(formData.get('count') || 0),
                length: Number(formData.get('length') || 16),
                maxUses: Number(formData.get('maxUses') || 1),
                expiresAt: String(formData.get('expiresAt') || '').trim(),
                remark: String(formData.get('remark') || '').trim(),
                commandTemplate: String(formData.get('commandTemplate') || '').trim()
            };

            if (payload.categoryId === '') {
                payload.categoryId = null;
            }

            var res = await request('/api/admin/redeem/keys/batch', {
                method: 'POST',
                body: payload
            });

            if (!res.ok || !res.payload.success) {
                showError(res.payload.message || '批量生成失败');
                return;
            }

            var csv = String(res.payload.csv || '');
            var filename = String(res.payload.filename || ('redeem_keys_' + Date.now() + '.csv'));
            if (csv) {
                var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                var link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href);
            }

            var createdCount = Array.isArray(res.payload.items) ? res.payload.items.length : 0;
            document.getElementById('redeem-batch-result').textContent = '已生成 ' + createdCount + ' 条';

            await Promise.all([loadKeys(1), loadStats()]);
        });

        document.getElementById('redeem-category-create-form').addEventListener('submit', async function (event) {
            event.preventDefault();
            var form = event.target;
            var formData = new FormData(form);
            var payload = {
                name: String(formData.get('name') || '').trim(),
                status: String(formData.get('status') || 'enabled').trim(),
                description: String(formData.get('description') || '').trim(),
                defaultCommandTemplate: String(formData.get('defaultCommandTemplate') || '').trim()
            };

            var res = await request('/api/admin/redeem/categories', {
                method: 'POST',
                body: payload
            });
            if (!res.ok || !res.payload.success) {
                showError(res.payload.message || '创建分类失败');
                return;
            }

            form.reset();
            await loadCategories();
        });

        document.getElementById('redeem-categories-table').addEventListener('click', async function (event) {
            var target = event.target;
            var row = target.closest('tr[data-category-id]');
            if (!row) return;
            var id = Number(row.getAttribute('data-category-id'));
            if (!id) return;

            if (target.closest('.redeem-category-save')) {
                var updatePayload = {
                    name: String(row.querySelector('.redeem-category-name').value || '').trim(),
                    status: String(row.querySelector('.redeem-category-status').value || 'enabled').trim(),
                    description: String(row.querySelector('.redeem-category-description').value || '').trim(),
                    defaultCommandTemplate: String(row.querySelector('.redeem-category-template').value || '').trim()
                };

                var updateRes = await request('/api/admin/redeem/categories/' + id, {
                    method: 'PATCH',
                    body: updatePayload
                });

                if (!updateRes.ok || !updateRes.payload.success) {
                    showError(updateRes.payload.message || '更新分类失败');
                    return;
                }

                await loadCategories();
                return;
            }

            if (target.closest('.redeem-category-delete')) {
                if (!window.confirm('确认删除该分类？分类下有卡密时将被拒绝。')) {
                    return;
                }

                var deleteRes = await request('/api/admin/redeem/categories/' + id, {
                    method: 'DELETE',
                    body: {}
                });

                if (!deleteRes.ok || !deleteRes.payload.success) {
                    showError(deleteRes.payload.message || '删除分类失败');
                    return;
                }

                await loadCategories();
                await Promise.all([loadKeys(state.keys.page), loadLogs(state.logs.page)]);
            }
        });

        document.getElementById('redeem-logs-refresh-btn').addEventListener('click', function () {
            loadLogs(1);
        });
        document.getElementById('redeem-logs-filter-status').addEventListener('change', function () { loadLogs(1); });
        document.getElementById('redeem-logs-filter-category').addEventListener('change', function () { loadLogs(1); });
        document.getElementById('redeem-logs-filter-per-page').addEventListener('change', function () { loadLogs(1); });
        document.getElementById('redeem-logs-filter-q').addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                loadLogs(1);
            }
        });

        document.getElementById('redeem-logs-prev').addEventListener('click', function () {
            if (state.logs.page > 1) {
                loadLogs(state.logs.page - 1);
            }
        });
        document.getElementById('redeem-logs-next').addEventListener('click', function () {
            if (state.logs.page < state.logs.totalPages) {
                loadLogs(state.logs.page + 1);
            }
        });
    }

    async function init() {
        await bindEvents();
        await loadCategories();
        await Promise.all([
            loadStats(),
            loadKeys(1),
            loadLogs(1)
        ]);
    }

    init();
})();

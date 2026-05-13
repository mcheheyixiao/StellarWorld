(function () {
    'use strict';

    var root = document.getElementById('signin-rewards-page');
    if (!root) {
        return;
    }

    var bootstrap = window.signinRewardsBootstrap || {};
    var initialRules = Array.isArray(bootstrap.rules) ? bootstrap.rules : [];
    var cardsRoot = document.getElementById('signin-reward-cards');
    var saveForm = document.getElementById('signin-rewards-save-form');
    var payloadInput = document.getElementById('signin-rewards-payload-json');
    var testForm = document.getElementById('signin-rewards-test-form');
    var previewMail = document.getElementById('signin-preview-mail');
    var previewItems = document.getElementById('signin-preview-items');
    var previewCommands = document.getElementById('signin-preview-commands');
    var previewPayload = document.getElementById('signin-preview-payload');
    var addButtons = root.querySelectorAll('[data-add-rule]');

    if (!cardsRoot || !saveForm || !payloadInput) {
        return;
    }

    function toInt(value, fallback) {
        var n = Number(value);
        if (!Number.isFinite(n)) {
            return fallback;
        }
        return Math.floor(n);
    }

    function normalizeRuleType(type) {
        var t = String(type || '').trim().toLowerCase();
        if (t === 'daily' || t === 'streak' || t === 'total' || t === 'monthly') {
            return t;
        }
        return 'daily';
    }

    function normalizeRule(raw, index) {
        var ruleType = normalizeRuleType(raw.rule_type || raw.type || 'daily');
        var trigger = toInt(raw.trigger_day, 1);
        if (trigger < 1) {
            trigger = 1;
        }
        if (ruleType === 'daily') {
            trigger = 1;
        }

        var mailContent = Array.isArray(raw.mail_content) ? raw.mail_content : [];
        var items = Array.isArray(raw.items) ? raw.items : [];
        var commands = Array.isArray(raw.commands) ? raw.commands : [];

        return {
            id: toInt(raw.id, 0),
            rule_type: ruleType,
            trigger_day: trigger,
            mail_title: String(raw.mail_title || ''),
            mail_icon: String(raw.mail_icon || 'BOOK'),
            mail_content: mailContent.map(function (line) { return String(line || ''); }),
            items: items.map(function (item) {
                return {
                    type: String((item && item.type) || ''),
                    amount: Math.max(1, toInt(item && item.amount, 1))
                };
            }),
            commands: commands.map(function (cmd) { return String(cmd || ''); }),
            enabled: !!raw.enabled,
            sort_order: toInt(raw.sort_order, index)
        };
    }

    var rules = initialRules.map(function (rule, index) {
        return normalizeRule(rule, index);
    });

    if (rules.length === 0) {
        rules.push(normalizeRule({
            id: 0,
            rule_type: 'daily',
            trigger_day: 1,
            mail_title: '每日签到奖励',
            mail_icon: 'BOOK',
            mail_content: [
                '你完成了 {date} 的每日签到。',
                '连续签到：{continuous} 天',
                '累计签到：{total} 次'
            ],
            items: [{ type: 'minecraft:diamond', amount: 1 }],
            commands: [],
            enabled: true
        }, 0));
    }

    function defaultRuleForType(ruleType) {
        var trigger = 1;
        if (ruleType === 'streak') {
            trigger = 3;
        } else if (ruleType === 'total') {
            trigger = 10;
        } else if (ruleType === 'monthly') {
            trigger = 7;
        }
        return normalizeRule({
            id: 0,
            rule_type: ruleType,
            trigger_day: trigger,
            mail_title: '',
            mail_icon: 'BOOK',
            mail_content: [],
            items: [],
            commands: [],
            enabled: true
        }, rules.length);
    }

    function typeLabel(type) {
        if (type === 'daily') return '每日';
        if (type === 'streak') return '连续';
        if (type === 'total') return '累计';
        if (type === 'monthly') return '本月';
        return '规则';
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function createItemRow(item) {
        var wrapper = document.createElement('div');
        wrapper.className = 'grid grid-cols-1 gap-2 md:grid-cols-5';
        wrapper.innerHTML = [
            '<input type="text" class="md:col-span-3" data-item-type placeholder="minecraft:diamond" value="', escapeHtml(item.type || ''), '">',
            '<input type="number" class="md:col-span-1" data-item-amount min="1" value="', String(Math.max(1, toInt(item.amount, 1))), '">',
            '<button type="button" class="ta-btn ta-btn-secondary md:col-span-1" data-item-remove>删除</button>'
        ].join('');
        return wrapper;
    }

    function createCommandRow(command) {
        var wrapper = document.createElement('div');
        wrapper.className = 'grid grid-cols-1 gap-2 md:grid-cols-5';
        wrapper.innerHTML = [
            '<input type="text" class="md:col-span-4" data-command-text placeholder="give {player} minecraft:diamond 1" value="', escapeHtml(command || ''), '">',
            '<button type="button" class="ta-btn ta-btn-secondary md:col-span-1" data-command-remove>删除</button>'
        ].join('');
        return wrapper;
    }

    function submitDeleteRule(ruleId) {
        var csrfInput = saveForm.querySelector('input[name="csrf_token"]');
        if (!csrfInput || !csrfInput.value) {
            return;
        }
        var form = document.createElement('form');
        form.method = 'post';
        form.action = '/admin/signin-rewards/delete-rule';
        form.style.display = 'none';

        var csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = 'csrf_token';
        csrf.value = csrfInput.value;
        form.appendChild(csrf);

        var rule = document.createElement('input');
        rule.type = 'hidden';
        rule.name = 'rule_id';
        rule.value = String(ruleId);
        form.appendChild(rule);

        document.body.appendChild(form);
        form.submit();
    }

    function renderCards() {
        cardsRoot.innerHTML = '';
        rules.forEach(function (rule, index) {
            var card = document.createElement('section');
            card.className = 'rounded-xl border border-slate-300/20 p-4';
            card.setAttribute('data-rule-index', String(index));

            var header = document.createElement('div');
            header.className = 'flex flex-wrap items-center justify-between gap-2';
            header.innerHTML = [
                '<div>',
                '<p class="text-sm ta-help-text">', typeLabel(rule.rule_type), '奖励规则</p>',
                '<p class="font-medium">触发天数：', String(rule.trigger_day), '</p>',
                '</div>',
                '<div class="flex flex-wrap gap-2">',
                '<label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" data-field="enabled" ', rule.enabled ? 'checked' : '', '> 启用</label>',
                '<button type="button" class="ta-btn ta-btn-secondary" data-action="copy">复制</button>',
                '<button type="button" class="ta-btn ta-btn-secondary" data-action="delete">删除</button>',
                '</div>'
            ].join('');
            card.appendChild(header);

            var body = document.createElement('div');
            body.className = 'mt-3 grid grid-cols-1 gap-3 md:grid-cols-2';
            body.innerHTML = [
                '<label class="block"><span class="text-sm">规则类型</span>',
                '<select data-field="rule_type">',
                '<option value="daily"', rule.rule_type === 'daily' ? ' selected' : '', '>daily（每日）</option>',
                '<option value="streak"', rule.rule_type === 'streak' ? ' selected' : '', '>streak（连续）</option>',
                '<option value="total"', rule.rule_type === 'total' ? ' selected' : '', '>total（累计）</option>',
                '<option value="monthly"', rule.rule_type === 'monthly' ? ' selected' : '', '>monthly（本月）</option>',
                '</select></label>',
                '<label class="block"><span class="text-sm">触发天数</span>',
                '<input type="number" min="1" data-field="trigger_day" value="', String(rule.trigger_day), '"', rule.rule_type === 'daily' ? ' readonly' : '', '>',
                '</label>',
                '<label class="block md:col-span-2"><span class="text-sm">邮件标题</span>',
                '<input type="text" data-field="mail_title" value="', escapeHtml(rule.mail_title || ''), '"></label>',
                '<label class="block md:col-span-2"><span class="text-sm">邮件图标</span>',
                '<input type="text" data-field="mail_icon" value="', escapeHtml(rule.mail_icon || 'BOOK'), '"></label>',
                '<label class="block md:col-span-2"><span class="text-sm">邮件正文（每行一条）</span>',
                '<textarea rows="4" data-field="mail_content">', escapeHtml((rule.mail_content || []).join('\n')), '</textarea></label>'
            ].join('');
            card.appendChild(body);

            var itemsBox = document.createElement('div');
            itemsBox.className = 'mt-3 space-y-2';
            itemsBox.innerHTML = '<div class="flex items-center justify-between"><p class="text-sm font-medium">物品列表</p><button type="button" class="ta-btn ta-btn-secondary" data-action="add-item">添加物品</button></div>';
            var itemsList = document.createElement('div');
            itemsList.setAttribute('data-items-list', '1');
            itemsList.className = 'space-y-2';
            (rule.items || []).forEach(function (item) {
                itemsList.appendChild(createItemRow(item));
            });
            if (itemsList.children.length === 0) {
                itemsList.appendChild(createItemRow({ type: '', amount: 1 }));
            }
            itemsBox.appendChild(itemsList);
            card.appendChild(itemsBox);

            var commandsBox = document.createElement('div');
            commandsBox.className = 'mt-3 space-y-2';
            commandsBox.innerHTML = '<div class="flex items-center justify-between"><p class="text-sm font-medium">命令列表</p><button type="button" class="ta-btn ta-btn-secondary" data-action="add-command">添加命令</button></div>';
            var commandsList = document.createElement('div');
            commandsList.setAttribute('data-commands-list', '1');
            commandsList.className = 'space-y-2';
            (rule.commands || []).forEach(function (command) {
                commandsList.appendChild(createCommandRow(command));
            });
            if (commandsList.children.length === 0) {
                commandsList.appendChild(createCommandRow(''));
            }
            commandsBox.appendChild(commandsList);
            card.appendChild(commandsBox);

            cardsRoot.appendChild(card);
        });

        refreshPreview();
    }

    function readItems(listNode) {
        var rows = [];
        listNode.querySelectorAll('[data-item-type]').forEach(function (inputNode) {
            var row = inputNode.closest('div');
            var typeNode = row ? row.querySelector('[data-item-type]') : null;
            var amountNode = row ? row.querySelector('[data-item-amount]') : null;
            var type = typeNode ? String(typeNode.value || '').trim() : '';
            var amount = amountNode ? Math.max(1, toInt(amountNode.value, 1)) : 1;
            if (type !== '') {
                rows.push({ type: type, amount: amount });
            }
        });
        return rows;
    }

    function readCommands(listNode) {
        var rows = [];
        listNode.querySelectorAll('[data-command-text]').forEach(function (inputNode) {
            var text = String(inputNode.value || '').trim();
            if (text !== '') {
                rows.push(text);
            }
        });
        return rows;
    }

    function syncRulesFromDom() {
        var nextRules = [];
        cardsRoot.querySelectorAll('[data-rule-index]').forEach(function (card, index) {
            var source = rules[index] || {};
            var ruleTypeNode = card.querySelector('[data-field="rule_type"]');
            var triggerNode = card.querySelector('[data-field="trigger_day"]');
            var titleNode = card.querySelector('[data-field="mail_title"]');
            var iconNode = card.querySelector('[data-field="mail_icon"]');
            var contentNode = card.querySelector('[data-field="mail_content"]');
            var enabledNode = card.querySelector('[data-field="enabled"]');
            var itemsList = card.querySelector('[data-items-list]');
            var commandsList = card.querySelector('[data-commands-list]');

            var ruleType = normalizeRuleType(ruleTypeNode ? ruleTypeNode.value : source.rule_type);
            var triggerDay = Math.max(1, toInt(triggerNode ? triggerNode.value : source.trigger_day, 1));
            if (ruleType === 'daily') {
                triggerDay = 1;
                if (triggerNode) {
                    triggerNode.value = '1';
                    triggerNode.setAttribute('readonly', 'readonly');
                }
            } else if (triggerNode) {
                triggerNode.removeAttribute('readonly');
            }

            var mailLines = [];
            String(contentNode ? contentNode.value : '').split(/\r\n|\r|\n/).forEach(function (line) {
                var text = String(line || '').trim();
                if (text !== '') {
                    mailLines.push(text);
                }
            });

            nextRules.push({
                id: toInt(source.id, 0),
                rule_type: ruleType,
                trigger_day: triggerDay,
                mail_title: String(titleNode ? titleNode.value : '').trim(),
                mail_icon: String(iconNode ? iconNode.value : '').trim() || 'BOOK',
                mail_content: mailLines,
                items: itemsList ? readItems(itemsList) : [],
                commands: commandsList ? readCommands(commandsList) : [],
                enabled: !!(enabledNode && enabledNode.checked),
                sort_order: index
            });
        });
        rules = nextRules;
    }

    function resolveScenario() {
        var continuous = 1;
        var total = 1;
        var monthDays = 1;
        var signDate = new Date().toISOString().slice(0, 10);
        var player = '';
        var uuid = '';
        var serverId = bootstrap.defaultServerId || 'survival-1';

        if (testForm) {
            var continuousNode = testForm.querySelector('input[name="continuous"]');
            var totalNode = testForm.querySelector('input[name="total"]');
            var monthNode = testForm.querySelector('input[name="month_days"]');
            var dateNode = testForm.querySelector('input[name="sign_date"]');
            var playerNode = testForm.querySelector('input[name="target_player_name"]');
            var uuidNode = testForm.querySelector('input[name="target_player_uuid"]');
            var serverNode = testForm.querySelector('input[name="server_id"]');
            continuous = Math.max(1, toInt(continuousNode ? continuousNode.value : 1, 1));
            total = Math.max(1, toInt(totalNode ? totalNode.value : continuous, continuous));
            monthDays = Math.max(1, toInt(monthNode ? monthNode.value : 1, 1));
            signDate = String(dateNode ? dateNode.value : signDate).trim() || signDate;
            player = String(playerNode ? playerNode.value : '').trim();
            uuid = String(uuidNode ? uuidNode.value : '').trim();
            serverId = String(serverNode ? serverNode.value : serverId).trim() || serverId;
        }

        return {
            signDate: signDate,
            continuous: continuous,
            total: total,
            month_days: monthDays,
            player: player,
            uuid: uuid,
            server_id: serverId
        };
    }

    function renderTokens(text, scenario) {
        return String(text || '')
            .replace(/\{player\}/g, scenario.player)
            .replace(/\{uuid\}/g, scenario.uuid)
            .replace(/\{date\}/g, scenario.signDate)
            .replace(/\{continuous\}/g, String(scenario.continuous))
            .replace(/\{total\}/g, String(scenario.total))
            .replace(/\{month_days\}/g, String(scenario.month_days))
            .replace(/\{server_id\}/g, String(scenario.server_id));
    }

    function mergeItems(groups) {
        var result = [];
        var pos = {};
        groups.forEach(function (items) {
            if (!Array.isArray(items)) return;
            items.forEach(function (item) {
                var type = String((item && item.type) || '').trim();
                if (type === '') return;
                var amount = Math.max(1, toInt(item.amount, 1));
                if (typeof pos[type] === 'undefined') {
                    pos[type] = result.length;
                    result.push({ type: type, amount: amount });
                    return;
                }
                result[pos[type]].amount += amount;
            });
        });
        return result;
    }

    function mergeCommands(groups, scenario) {
        var result = [];
        var seen = {};
        groups.forEach(function (commands) {
            if (!Array.isArray(commands)) return;
            commands.forEach(function (command) {
                var text = String(command || '').trim();
                if (text === '' || /[\r\n]/.test(text)) return;
                text = text.replace(/^\/+/, '').trim();
                if (text === '') return;
                text = renderTokens(text, scenario);
                if (text === '' || /[\r\n]/.test(text) || seen[text]) return;
                seen[text] = true;
                result.push(text);
            });
        });
        return result;
    }

    function buildPreviewPayload() {
        syncRulesFromDom();
        var scenario = resolveScenario();
        var matched = [];

        rules.forEach(function (rule) {
            if (!rule.enabled) return;
            if (rule.rule_type === 'daily') {
                matched.push(rule);
                return;
            }
            if (rule.rule_type === 'streak' && rule.trigger_day === scenario.continuous) {
                matched.push(rule);
                return;
            }
            if (rule.rule_type === 'total' && rule.trigger_day === scenario.total) {
                matched.push(rule);
                return;
            }
            if (rule.rule_type === 'monthly' && rule.trigger_day === scenario.month_days) {
                matched.push(rule);
            }
        });

        var primary = matched.length > 0 ? matched[0] : null;
        var mailTitle = primary && primary.mail_title ? primary.mail_title : '每日签到奖励';
        var mailIcon = primary && primary.mail_icon ? primary.mail_icon : 'BOOK';
        var mailContent = primary && Array.isArray(primary.mail_content) && primary.mail_content.length > 0
            ? primary.mail_content.slice()
            : ['你完成了 {date} 的每日签到。'];

        matched.slice(1).forEach(function (rule) {
            (rule.mail_content || []).forEach(function (line) {
                var text = String(line || '').trim();
                if (text !== '') {
                    mailContent.push(text);
                }
            });
        });

        var mergedItems = mergeItems(matched.map(function (rule) { return rule.items || []; }));
        var mergedCommands = mergeCommands(matched.map(function (rule) { return rule.commands || []; }), scenario);

        var payload = {
            mail: {
                title: renderTokens(mailTitle, scenario),
                icon: mailIcon,
                content: mailContent.map(function (line) { return renderTokens(line, scenario); })
            },
            items: mergedItems,
            commands: mergedCommands,
            meta: {
                signDate: scenario.signDate,
                continuous: scenario.continuous,
                total: scenario.total,
                month_days: scenario.month_days,
                source: 'preview',
                matchedRules: matched.map(function (rule) {
                    return {
                        rule_id: toInt(rule.id, 0),
                        rule_type: rule.rule_type,
                        trigger_day: rule.trigger_day
                    };
                })
            }
        };

        if (payload.items.length === 0 && payload.commands.length === 0) {
            payload.meta.rewardEmpty = true;
        }

        return payload;
    }

    function refreshPreview() {
        var payload = buildPreviewPayload();
        if (previewMail) {
            previewMail.textContent = JSON.stringify(payload.mail, null, 2);
        }
        if (previewItems) {
            previewItems.textContent = JSON.stringify(payload.items, null, 2);
        }
        if (previewCommands) {
            previewCommands.textContent = JSON.stringify(payload.commands, null, 2);
        }
        if (previewPayload) {
            previewPayload.textContent = JSON.stringify(payload, null, 2);
        }
    }

    cardsRoot.addEventListener('input', function () {
        refreshPreview();
    });

    cardsRoot.addEventListener('change', function () {
        refreshPreview();
    });

    cardsRoot.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLElement)) return;
        var card = target.closest('[data-rule-index]');
        if (!card) return;
        var index = toInt(card.getAttribute('data-rule-index'), -1);
        if (index < 0 || index >= rules.length) return;

        if (target.matches('[data-item-remove]')) {
            var itemRow = target.closest('.grid');
            if (itemRow) {
                itemRow.remove();
                refreshPreview();
            }
            return;
        }
        if (target.matches('[data-command-remove]')) {
            var commandRow = target.closest('.grid');
            if (commandRow) {
                commandRow.remove();
                refreshPreview();
            }
            return;
        }

        var action = target.getAttribute('data-action');
        if (!action) return;

        if (action === 'add-item') {
            var itemsList = card.querySelector('[data-items-list]');
            if (itemsList) {
                itemsList.appendChild(createItemRow({ type: '', amount: 1 }));
                refreshPreview();
            }
            return;
        }
        if (action === 'add-command') {
            var commandsList = card.querySelector('[data-commands-list]');
            if (commandsList) {
                commandsList.appendChild(createCommandRow(''));
                refreshPreview();
            }
            return;
        }
        if (action === 'copy') {
            syncRulesFromDom();
            var clone = JSON.parse(JSON.stringify(rules[index]));
            clone.id = 0;
            clone.sort_order = rules.length;
            rules.splice(index + 1, 0, clone);
            renderCards();
            return;
        }
        if (action === 'delete') {
            syncRulesFromDom();
            var rule = rules[index];
            if (toInt(rule.id, 0) > 0) {
                if (window.confirm('确定要从数据库草稿中删除这条已存在规则吗？')) {
                    submitDeleteRule(toInt(rule.id, 0));
                }
                return;
            }
            rules.splice(index, 1);
            if (rules.length === 0) {
                rules.push(defaultRuleForType('daily'));
            }
            renderCards();
        }
    });

    addButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            syncRulesFromDom();
            var type = normalizeRuleType(button.getAttribute('data-add-rule') || 'daily');
            rules.push(defaultRuleForType(type));
            renderCards();
        });
    });

    if (testForm) {
        testForm.addEventListener('input', function () {
            refreshPreview();
        });
        testForm.addEventListener('change', function () {
            refreshPreview();
        });
    }

    saveForm.addEventListener('submit', function () {
        syncRulesFromDom();
        var draftNameNode = document.getElementById('signin-draft-name');
        payloadInput.value = JSON.stringify({
            name: draftNameNode ? String(draftNameNode.value || '').trim() : '',
            rules: rules,
            admin_repeat_test_enabled: (saveForm.querySelector('input[name="admin_repeat_test_enabled"]') || {}).checked ? 1 : 0
        });
    });

    renderCards();
})();

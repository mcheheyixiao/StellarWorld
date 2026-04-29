(function () {
    'use strict';

    var page = document.getElementById('checkin-rewards-page');
    if (!page) {
        return;
    }

    var monthLabel = page.querySelector('[data-checkin-month-label]');
    var calendarGrid = page.querySelector('[data-checkin-calendar-grid]');
    var previewMeta = page.querySelector('[data-checkin-preview-meta]');
    var jsonPreview = page.querySelector('[data-checkin-json-preview]');
    var prevBtn = page.querySelector('[data-checkin-month-prev]');
    var nextBtn = page.querySelector('[data-checkin-month-next]');
    var resetBtn = page.querySelector('[data-checkin-reset-month]');
    var openEditorBtn = page.querySelector('[data-checkin-open-editor]');

    var modal = page.querySelector('[data-checkin-modal]');
    var modalTitle = page.querySelector('[data-checkin-modal-title]');
    var closeButtons = page.querySelectorAll('[data-checkin-modal-close]');
    var saveBtn = page.querySelector('[data-checkin-save]');

    var coinsInput = page.querySelector('[data-checkin-input-coins]');
    var streakInput = page.querySelector('[data-checkin-input-streak]');
    var scopeInputs = page.querySelectorAll('[data-checkin-scope]');
    var itemList = page.querySelector('[data-checkin-item-list]');
    var commandList = page.querySelector('[data-checkin-command-list]');
    var addItemBtn = page.querySelector('[data-checkin-add-item]');
    var addCommandBtn = page.querySelector('[data-checkin-add-command]');
    var itemTemplate = page.querySelector('[data-checkin-item-template]');
    var commandTemplate = page.querySelector('[data-checkin-command-template]');

    var today = new Date();
    var displayMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    var selectedDay = today.getDate();
    var rewardStore = {};

    function monthKey(dateObj) {
        return dateObj.getFullYear() + '-' + String(dateObj.getMonth() + 1).padStart(2, '0');
    }

    function formatMonth(dateObj) {
        return dateObj.getFullYear() + '年' + String(dateObj.getMonth() + 1).padStart(2, '0') + '月';
    }

    function daysInMonth(dateObj) {
        return new Date(dateObj.getFullYear(), dateObj.getMonth() + 1, 0).getDate();
    }

    function buildDefaultMonthData(dateObj) {
        var count = daysInMonth(dateObj);
        var records = {};
        for (var day = 1; day <= count; day++) {
            records[day] = {
                coins: 100 + day * 5,
                streak: 10 + (day % 4),
                items: [
                    {
                        name: 'minecraft:gold_nugget',
                        amount: (day % 3) + 1
                    }
                ],
                commands: [
                    'say [Check-in] day ' + day + ' reward delivered'
                ],
                scope: 'month',
                modified: false
            };
        }
        return records;
    }

    function ensureMonthData(dateObj) {
        var key = monthKey(dateObj);
        if (!rewardStore[key]) {
            rewardStore[key] = buildDefaultMonthData(dateObj);
        }
        return rewardStore[key];
    }

    function getCurrentMonthData() {
        return ensureMonthData(displayMonth);
    }

    function getSelectedReward() {
        var monthData = getCurrentMonthData();
        var maxDay = daysInMonth(displayMonth);
        if (selectedDay > maxDay) {
            selectedDay = maxDay;
        }
        if (selectedDay < 1) {
            selectedDay = 1;
        }
        return monthData[selectedDay];
    }

    function isToday(day) {
        return (
            today.getFullYear() === displayMonth.getFullYear() &&
            today.getMonth() === displayMonth.getMonth() &&
            today.getDate() === day
        );
    }

    function setScopeValue(scopeValue) {
        scopeInputs.forEach(function (input) {
            input.checked = input.value === scopeValue;
        });
    }

    function getScopeValue() {
        var active = 'month';
        scopeInputs.forEach(function (input) {
            if (input.checked) {
                active = input.value;
            }
        });
        return active;
    }

    function createItemRow(item) {
        var fragment = itemTemplate.content.cloneNode(true);
        var wrapper = fragment.querySelector('.ta-checkin-input-card');
        var nameInput = wrapper.querySelector('[data-checkin-item-name]');
        var amountInput = wrapper.querySelector('[data-checkin-item-amount]');
        var removeBtn = wrapper.querySelector('[data-checkin-remove-row]');
        if (item) {
            nameInput.value = item.name || '';
            amountInput.value = item.amount || 1;
        }
        removeBtn.addEventListener('click', function () {
            wrapper.remove();
        });
        itemList.appendChild(fragment);
    }

    function createCommandRow(commandText) {
        var fragment = commandTemplate.content.cloneNode(true);
        var wrapper = fragment.querySelector('.ta-checkin-input-card');
        var commandInput = wrapper.querySelector('[data-checkin-command-text]');
        var removeBtn = wrapper.querySelector('[data-checkin-remove-row]');
        if (commandText) {
            commandInput.value = commandText;
        }
        removeBtn.addEventListener('click', function () {
            wrapper.remove();
        });
        commandList.appendChild(fragment);
    }

    function fillModalFields(day) {
        var reward = getCurrentMonthData()[day];
        modalTitle.textContent = '编辑 ' + day + ' 号奖励';
        coinsInput.value = reward.coins;
        streakInput.value = reward.streak;
        setScopeValue(reward.scope || 'month');

        itemList.innerHTML = '';
        commandList.innerHTML = '';

        if (reward.items && reward.items.length) {
            reward.items.forEach(function (item) {
                createItemRow(item);
            });
        } else {
            createItemRow({ name: '', amount: 1 });
        }

        if (reward.commands && reward.commands.length) {
            reward.commands.forEach(function (commandText) {
                createCommandRow(commandText);
            });
        } else {
            createCommandRow('');
        }
    }

    function readItemsFromModal() {
        var rows = [];
        itemList.querySelectorAll('.ta-checkin-input-card').forEach(function (row) {
            var nameInput = row.querySelector('[data-checkin-item-name]');
            var amountInput = row.querySelector('[data-checkin-item-amount]');
            var name = nameInput ? String(nameInput.value || '').trim() : '';
            var amount = amountInput ? Number(amountInput.value) : 1;
            if (name !== '') {
                rows.push({
                    name: name,
                    amount: Number.isFinite(amount) && amount > 0 ? amount : 1
                });
            }
        });
        return rows;
    }

    function readCommandsFromModal() {
        var rows = [];
        commandList.querySelectorAll('.ta-checkin-input-card').forEach(function (row) {
            var commandInput = row.querySelector('[data-checkin-command-text]');
            var cmd = commandInput ? String(commandInput.value || '').trim() : '';
            if (cmd !== '') {
                rows.push(cmd);
            }
        });
        return rows;
    }

    function renderPreview() {
        var reward = getSelectedReward();
        previewMeta.innerHTML = '';

        var lines = [
            '日期：' + selectedDay + '号',
            '金币：' + reward.coins,
            '连续加成：' + reward.streak,
            '物品数量：' + (reward.items ? reward.items.length : 0),
            '命令数量：' + (reward.commands ? reward.commands.length : 0),
            '作用范围：' + (reward.scope === 'global' ? '全局模板' : '仅本月'),
            '状态：' + (reward.modified ? '已修改' : '默认')
        ];

        lines.forEach(function (line) {
            var row = document.createElement('p');
            row.className = 'ta-checkin-preview-line';
            row.textContent = line;
            previewMeta.appendChild(row);
        });

        jsonPreview.textContent = JSON.stringify(
            {
                day: selectedDay,
                reward: reward
            },
            null,
            2
        );
    }

    function renderCalendar() {
        var monthData = getCurrentMonthData();
        var totalDays = daysInMonth(displayMonth);
        var firstDay = new Date(displayMonth.getFullYear(), displayMonth.getMonth(), 1).getDay();
        var mondayIndex = (firstDay + 6) % 7;

        monthLabel.textContent = formatMonth(displayMonth);
        calendarGrid.innerHTML = '';

        for (var blank = 0; blank < mondayIndex; blank++) {
            var placeholder = document.createElement('div');
            placeholder.className = 'ta-checkin-day ta-checkin-day--placeholder';
            calendarGrid.appendChild(placeholder);
        }

        for (var day = 1; day <= totalDays; day++) {
            var reward = monthData[day];
            var dayCard = document.createElement('button');
            dayCard.type = 'button';
            dayCard.className = 'ta-checkin-day transition-all hover:scale-[1.02]';
            if (reward.modified) {
                dayCard.classList.add('ta-checkin-day--modified');
            }
            if (isToday(day)) {
                dayCard.classList.add('ta-checkin-day--today');
            }
            if (day === selectedDay) {
                dayCard.classList.add('ta-checkin-day--selected');
            }

            dayCard.innerHTML =
                '<span class="ta-checkin-day-number">' + day + '号</span>' +
                '<span class="ta-checkin-day-line">🪙 ' + reward.coins + '</span>' +
                '<span class="ta-checkin-day-line">⛓ ' + reward.streak + '</span>';

            (function (dayValue) {
                dayCard.addEventListener('click', function () {
                    selectedDay = dayValue;
                    renderCalendar();
                    renderPreview();
                    openModal();
                });
            })(day);

            calendarGrid.appendChild(dayCard);
        }
    }

    function openModal() {
        fillModalFields(selectedDay);
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
    }

    prevBtn.addEventListener('click', function () {
        displayMonth = new Date(displayMonth.getFullYear(), displayMonth.getMonth() - 1, 1);
        selectedDay = 1;
        renderCalendar();
        renderPreview();
    });

    nextBtn.addEventListener('click', function () {
        displayMonth = new Date(displayMonth.getFullYear(), displayMonth.getMonth() + 1, 1);
        selectedDay = 1;
        renderCalendar();
        renderPreview();
    });

    resetBtn.addEventListener('click', function () {
        rewardStore[monthKey(displayMonth)] = buildDefaultMonthData(displayMonth);
        selectedDay = 1;
        renderCalendar();
        renderPreview();
    });

    openEditorBtn.addEventListener('click', function () {
        openModal();
    });

    closeButtons.forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });

    addItemBtn.addEventListener('click', function () {
        createItemRow({ name: '', amount: 1 });
    });

    addCommandBtn.addEventListener('click', function () {
        createCommandRow('');
    });

    saveBtn.addEventListener('click', function () {
        var reward = getSelectedReward();
        var coinsValue = Number(coinsInput.value);
        var streakValue = Number(streakInput.value);
        reward.coins = Number.isFinite(coinsValue) && coinsValue >= 0 ? coinsValue : 0;
        reward.streak = Number.isFinite(streakValue) && streakValue >= 0 ? streakValue : 0;
        reward.items = readItemsFromModal();
        reward.commands = readCommandsFromModal();
        reward.scope = getScopeValue();
        reward.modified = true;

        closeModal();
        renderCalendar();
        renderPreview();
    });

    renderCalendar();
    renderPreview();
})();

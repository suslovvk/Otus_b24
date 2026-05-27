/**
 * Matrix CRM Tab — логика вкладки в карточке контакта Битрикс24.
 */
var MatrixCrmTab = (function () {
    'use strict';

    var cfg = {};            // конфиг (contactId, patientId, ajaxUrl, sessid)
    var bookState = {};      // состояние формы новой записи

    // ── Инициализация ────────────────────────────────────────────────────────

    function init(options) {
        cfg = options;
        if (!cfg.patientId) return; // пациент не привязан

        setupTabs();
        loadPane('balance');    // загружаем первую вкладку сразу
    }

    // ── Переключение вкладок ─────────────────────────────────────────────────

    function setupTabs() {
        var nav = document.querySelectorAll('#matrix-crm-tab .matrix-tab-btn');
        nav.forEach(function (btn) {
            btn.addEventListener('click', function () {
                nav.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');

                var paneId = btn.getAttribute('data-pane');
                document.querySelectorAll('#matrix-crm-tab .matrix-pane').forEach(function (p) {
                    p.classList.remove('active');
                });
                var pane = document.getElementById('matrix-pane-' + paneId);
                if (pane) {
                    pane.classList.add('active');
                    if (!pane._loaded) loadPane(paneId);
                }
            });
        });
    }

    function loadPane(name) {
        switch (name) {
            case 'balance':  renderBalance();  break;
            case 'records':  renderRecords(1); break;
            case 'book':     renderBooking();  break;
            case 'turnouts': renderTurnouts(1); break;
            case 'receipts': renderReceipts(1); break;
        }
    }

    // ── Утилиты ──────────────────────────────────────────────────────────────

    function post(action, extra, callback) {
        var fd = new FormData();
        fd.append('action',     action);
        fd.append('contact_id', cfg.contactId);
        fd.append('patient_id', cfg.patientId);
        fd.append('sessid',     cfg.sessid);
        if (extra) {
            Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });
        }
        fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(callback)
            .catch(function (e) { console.error('MatrixCrmTab AJAX error:', e); });
    }

    function pane(name)  { return document.getElementById('matrix-pane-' + name); }
    function setHtml(el, html) { if (el) el.innerHTML = html; }

    function fmtMoney(v) {
        return v === null || v === undefined ? '—' : Number(v).toFixed(2) + ' ₽';
    }
    function fmtDate(s) {
        if (!s) return '—';
        var d = new Date(s);
        return isNaN(d) ? s : d.toLocaleDateString('ru-RU') + ' ' + d.toLocaleTimeString('ru-RU', {hour:'2-digit',minute:'2-digit'});
    }
    function fmtDateOnly(s) {
        if (!s) return '—';
        var d = new Date(s);
        return isNaN(d) ? s : d.toLocaleDateString('ru-RU');
    }
    function esc(s) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(s)));
        return div.innerHTML;
    }

    // ── Баланс ───────────────────────────────────────────────────────────────

    function renderBalance() {
        var el = pane('balance');
        setHtml(el, '<div class="matrix-loading">Загрузка...</div>');

        post('get_balance', {}, function (data) {
            if (!data.success) { setHtml(el, '<div class="matrix-error">' + esc(data.message) + '</div>'); return; }

            var html = '<div class="matrix-balance-grid">'
                + '<div class="matrix-balance-card"><div class="label">Баланс</div>'
                + '<div class="value">' + fmtMoney(data.balance) + '</div>'
                + '<div class="date">' + esc(data.balance_date || '') + '</div></div>'
                + '<div class="matrix-balance-card"><div class="label">Бонусы</div>'
                + '<div class="value">' + fmtMoney(data.bonus) + '</div>'
                + '<div class="date">' + esc(data.bonus_date || '') + '</div></div>'
                + '</div>'
                + '<button class="matrix-btn matrix-btn-primary" onclick="MatrixCrmTab.openReplenish()">Пополнить баланс</button>'
                + '<button class="matrix-btn matrix-btn-default" onclick="MatrixCrmTab.openChargeBonus()">Начислить бонусы</button>'
                + '<button class="matrix-btn matrix-btn-default" onclick="MatrixCrmTab.syncPatient()">Синхронизировать</button>'

                // Форма пополнения баланса
                + '<div class="matrix-inline-form" id="matrix-form-replenish">'
                + '<label>Сумма пополнения (₽)</label>'
                + '<input type="number" id="mx-replenish-sum" min="1" step="0.01" placeholder="500.00">'
                + '<label>Комментарий</label>'
                + '<input type="text" id="mx-replenish-comment" placeholder="Необязательно">'
                + '<button class="matrix-btn matrix-btn-primary" onclick="MatrixCrmTab.doReplenish()">Пополнить</button>'
                + '<button class="matrix-btn matrix-btn-default" onclick="MatrixCrmTab.closeForm(\'replenish\')">Отмена</button>'
                + '</div>'

                // Форма начисления бонусов
                + '<div class="matrix-inline-form" id="matrix-form-bonus">'
                + '<label>Количество бонусов</label>'
                + '<input type="number" id="mx-bonus-amount" min="1" step="1" placeholder="100">'
                + '<label>Дата активации</label>'
                + '<input type="date" id="mx-bonus-activated">'
                + '<label>Дата сгорания</label>'
                + '<input type="date" id="mx-bonus-deactivated">'
                + '<label>Примечание</label>'
                + '<input type="text" id="mx-bonus-note" placeholder="Необязательно">'
                + '<button class="matrix-btn matrix-btn-primary" onclick="MatrixCrmTab.doChargeBonus()">Начислить</button>'
                + '<button class="matrix-btn matrix-btn-default" onclick="MatrixCrmTab.closeForm(\'bonus\')">Отмена</button>'
                + '</div>';

            setHtml(el, html);
            el._loaded = true;
        });
    }

    function openReplenish()   { toggleForm('replenish'); }
    function openChargeBonus() { toggleForm('bonus'); }
    function toggleForm(name) {
        var el = document.getElementById('matrix-form-' + name);
        if (el) el.classList.toggle('open');
    }
    function closeForm(name) {
        var el = document.getElementById('matrix-form-' + name);
        if (el) el.classList.remove('open');
    }

    function doReplenish() {
        var sum     = document.getElementById('mx-replenish-sum').value;
        var comment = document.getElementById('mx-replenish-comment').value;
        post('replenish_balance', { sum: sum, comment: comment }, function (data) {
            alert(data.message || (data.success ? 'Готово' : 'Ошибка'));
            if (data.success) { pane('balance')._loaded = false; renderBalance(); }
        });
    }

    function doChargeBonus() {
        post('charge_bonus', {
            amount        : document.getElementById('mx-bonus-amount').value,
            activated_at  : document.getElementById('mx-bonus-activated').value,
            deactivated_at: document.getElementById('mx-bonus-deactivated').value,
            note          : document.getElementById('mx-bonus-note').value,
        }, function (data) {
            alert(data.message || (data.success ? 'Готово' : 'Ошибка'));
            if (data.success) closeForm('bonus');
        });
    }

    function syncPatient() {
        post('sync_patient', {}, function (data) {
            alert(data.message || (data.success ? 'Готово' : 'Ошибка'));
            if (data.success) {
                ['balance','records','turnouts','receipts'].forEach(function(p) {
                    var el = pane(p);
                    if (el) el._loaded = false;
                });
                renderBalance();
            }
        });
    }

    // ── Записи на приём ──────────────────────────────────────────────────────

    function renderRecords(page) {
        var el = pane('records');
        setHtml(el, '<div class="matrix-loading">Загрузка...</div>');

        post('get_records', { page: page }, function (data) {
            if (!data.success) { setHtml(el, '<div class="matrix-error">' + esc(data.message) + '</div>'); return; }

            if (!data.records || !data.records.length) {
                setHtml(el, '<div class="matrix-loading">Записей нет</div>'); el._loaded = true; return;
            }

            var html = '<table class="matrix-table"><thead><tr>'
                + '<th>Дата/Время</th><th>Врач</th><th>Процедура</th><th>Статус</th><th>Действие</th>'
                + '</tr></thead><tbody>';

            data.records.forEach(function (r) {
                var canceled = r.CANCELED == '1' || r.CANCELED === true;
                var status   = canceled
                    ? '<span class="matrix-tag matrix-tag-danger">Отменена</span>'
                    : '<span class="matrix-tag matrix-tag-ok">Активна</span>';
                var btn = (!canceled)
                    ? '<button class="matrix-btn matrix-btn-danger matrix-btn-sm" '
                      + 'onclick="MatrixCrmTab.cancelRecord(' + esc(r.MATRIX_RECORD_ID) + ')">Отменить</button>'
                    : '';
                html += '<tr>'
                    + '<td>' + esc(fmtDate(r.DATETIME)) + (r.IS_ONLINE == '1' ? ' <em>онлайн</em>' : '') + '</td>'
                    + '<td>' + esc(r.doctor_name) + '</td>'
                    + '<td>' + esc(r.procedure_name) + '</td>'
                    + '<td>' + status + '</td>'
                    + '<td>' + btn + '</td>'
                    + '</tr>';
            });

            html += '</tbody></table>';
            html += pagination(page, data.records.length, 15, 'MatrixCrmTab.loadRecords');

            setHtml(el, html);
            el._loaded = true;
        });
    }

    function cancelRecord(matrixRecordId) {
        if (!confirm('Отменить запись?')) return;
        post('cancel_record', { matrix_record_id: matrixRecordId }, function (data) {
            alert(data.message || (data.success ? 'Готово' : 'Ошибка'));
            if (data.success) { pane('records')._loaded = false; renderRecords(1); }
        });
    }

    // ── Форма новой записи ───────────────────────────────────────────────────

    function renderBooking() {
        var el = pane('book');
        bookState = { doctorId: 0, procedureId: 0, date: '', slot: '' };

        post('get_doctors', {}, function (data) {
            if (!data.success) { setHtml(el, '<div class="matrix-error">' + esc(data.message) + '</div>'); return; }

            var opts = '<option value="">— выберите врача —</option>';
            (data.doctors || []).forEach(function (d) {
                opts += '<option value="' + d.matrix_id + '">' + esc(d.name) + '</option>';
            });

            var html = '<div class="matrix-book-step">'
                + '<label>1. Врач</label>'
                + '<select id="mx-book-doctor" onchange="MatrixCrmTab.onDoctorChange(this.value)">' + opts + '</select>'
                + '</div>'
                + '<div class="matrix-book-step" id="mx-step-proc" style="display:none">'
                + '<label>2. Процедура</label>'
                + '<select id="mx-book-proc" onchange="MatrixCrmTab.onProcChange(this.value)"><option>—</option></select>'
                + '</div>'
                + '<div class="matrix-book-step" id="mx-step-date" style="display:none">'
                + '<label>3. Дата</label>'
                + '<input type="date" id="mx-book-date" onchange="MatrixCrmTab.onDateChange(this.value)">'
                + '</div>'
                + '<div class="matrix-book-step" id="mx-step-slots" style="display:none">'
                + '<label>4. Время</label>'
                + '<div class="matrix-slots" id="mx-book-slots"></div>'
                + '</div>'
                + '<div id="mx-step-confirm" style="display:none">'
                + '<button class="matrix-btn matrix-btn-primary" onclick="MatrixCrmTab.doCreateRecord()">Записать</button>'
                + '</div>';

            setHtml(el, html);
            el._loaded = true;
        });
    }

    function onDoctorChange(matrixDoctorId) {
        bookState.doctorId = parseInt(matrixDoctorId) || 0;
        bookState.procedureId = 0;
        bookState.date = '';
        bookState.slot = '';

        ['mx-step-proc','mx-step-date','mx-step-slots','mx-step-confirm'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });

        if (!bookState.doctorId) return;

        post('get_procedures', { matrix_doctor_id: matrixDoctorId }, function (data) {
            var opts = '<option value="">— выберите процедуру —</option>';
            (data.procedures || []).forEach(function (p) {
                opts += '<option value="' + p.id + '" data-dur="' + p.duration + '">'
                    + esc(p.name) + ' (' + p.duration + ' мин)' + '</option>';
            });
            var sel = document.getElementById('mx-book-proc');
            if (sel) {
                sel.innerHTML = opts;
                document.getElementById('mx-step-proc').style.display = '';
            }
        });
    }

    function onProcChange(procedureId) {
        bookState.procedureId = parseInt(procedureId) || 0;
        bookState.date = '';
        bookState.slot = '';
        ['mx-step-date','mx-step-slots','mx-step-confirm'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });
        if (!bookState.procedureId) return;
        document.getElementById('mx-step-date').style.display = '';
    }

    function onDateChange(dateVal) {
        bookState.date = dateVal;
        bookState.slot = '';
        var slotsEl = document.getElementById('mx-step-slots');
        var confirmEl = document.getElementById('mx-step-confirm');
        if (slotsEl) slotsEl.style.display = 'none';
        if (confirmEl) confirmEl.style.display = 'none';
        if (!dateVal) return;

        var slotsContainer = document.getElementById('mx-book-slots');
        setHtml(slotsContainer, '<em>Загрузка слотов...</em>');
        slotsEl.style.display = '';

        post('get_slots', {
            matrix_doctor_id:    bookState.doctorId,
            matrix_procedure_id: bookState.procedureId,
            date:                dateVal,
        }, function (data) {
            if (!data.success || !data.slots || !data.slots.length) {
                setHtml(slotsContainer, '<em>Нет свободных слотов</em>');
                return;
            }
            var html = '';
            data.slots.forEach(function (s) {
                var d = new Date(s);
                var label = isNaN(d) ? s : d.toLocaleTimeString('ru-RU', {hour:'2-digit',minute:'2-digit'});
                html += '<div class="matrix-slot" data-datetime="' + esc(s) + '" onclick="MatrixCrmTab.selectSlot(this)">'
                    + esc(label) + '</div>';
            });
            setHtml(slotsContainer, html);
        });
    }

    function selectSlot(el) {
        document.querySelectorAll('#mx-book-slots .matrix-slot').forEach(function(s) { s.classList.remove('selected'); });
        el.classList.add('selected');
        bookState.slot = el.getAttribute('data-datetime');
        var confirmEl = document.getElementById('mx-step-confirm');
        if (confirmEl) confirmEl.style.display = '';
    }

    function doCreateRecord() {
        if (!bookState.doctorId || !bookState.procedureId || !bookState.slot) {
            alert('Выберите врача, процедуру и время');
            return;
        }
        post('create_record', {
            matrix_doctor_id:    bookState.doctorId,
            matrix_procedure_id: bookState.procedureId,
            datetime:            bookState.slot,
        }, function (data) {
            alert(data.message || (data.success ? 'Готово' : 'Ошибка'));
            if (data.success) {
                pane('records')._loaded = false;
                pane('book')._loaded = false;
                renderBooking();
            }
        });
    }

    // ── Контрольные явки ─────────────────────────────────────────────────────

    function renderTurnouts(page) {
        var el = pane('turnouts');
        setHtml(el, '<div class="matrix-loading">Загрузка...</div>');

        post('get_turnouts', { page: page }, function (data) {
            if (!data.success) { setHtml(el, '<div class="matrix-error">' + esc(data.message) + '</div>'); return; }

            if (!data.turnouts || !data.turnouts.length) {
                setHtml(el, '<div class="matrix-loading">Явок нет</div>'); el._loaded = true; return;
            }

            var html = '<table class="matrix-table"><thead><tr>'
                + '<th>Дата</th><th>Врач</th><th>Уведомлено</th><th>Дело CRM</th>'
                + '</tr></thead><tbody>';

            data.turnouts.forEach(function (t) {
                var notified = (t.NOTIFIED === true || t.NOTIFIED == '1')
                    ? '<span class="matrix-tag matrix-tag-ok">Да</span>'
                    : '<span class="matrix-tag matrix-tag-warn">Нет</span>';
                var actId    = parseInt(t.BITRIX_ACTIVITY_ID) || 0;
                var actLink  = actId ? '<a href="/crm/activity/' + actId + '/" target="_blank">#' + actId + '</a>' : '—';
                html += '<tr>'
                    + '<td>' + esc(fmtDateOnly(t.TURNOUT_DATE)) + '</td>'
                    + '<td>' + esc(t.doctor_name || '—') + '</td>'
                    + '<td>' + notified + '</td>'
                    + '<td>' + actLink + '</td>'
                    + '</tr>';
            });

            html += '</tbody></table>';
            html += pagination(page, data.turnouts.length, 15, 'MatrixCrmTab.loadTurnouts');

            setHtml(el, html);
            el._loaded = true;
        });
    }

    // ── Квитанции ────────────────────────────────────────────────────────────

    function renderReceipts(page) {
        var el = pane('receipts');
        setHtml(el, '<div class="matrix-loading">Загрузка...</div>');

        post('get_receipts', { page: page }, function (data) {
            if (!data.success) { setHtml(el, '<div class="matrix-error">' + esc(data.message) + '</div>'); return; }

            if (!data.receipts || !data.receipts.length) {
                setHtml(el, '<div class="matrix-loading">Квитанций нет</div>'); el._loaded = true; return;
            }

            var html = '<table class="matrix-table"><thead><tr>'
                + '<th>Чек #</th><th>Дата</th><th>Сумма</th><th>Сделка CRM</th>'
                + '</tr></thead><tbody>';

            data.receipts.forEach(function (r) {
                var dealId  = parseInt(r.BITRIX_DEAL_ID) || 0;
                var dealLink = dealId
                    ? '<a href="/crm/deal/' + dealId + '/" target="_blank">#' + dealId + '</a>'
                    : '—';
                html += '<tr>'
                    + '<td>' + esc(r.MATRIX_RECEIPT_ID) + '</td>'
                    + '<td>' + esc(fmtDate(r.CHECKED_AT)) + '</td>'
                    + '<td>' + esc(fmtMoney(r.SUM)) + '</td>'
                    + '<td>' + dealLink + '</td>'
                    + '</tr>';
            });

            html += '</tbody></table>';
            html += pagination(page, data.receipts.length, 15, 'MatrixCrmTab.loadReceipts');

            setHtml(el, html);
            el._loaded = true;
        });
    }

    // ── Пагинация ────────────────────────────────────────────────────────────

    function pagination(page, count, limit, fn) {
        var html = '<div class="matrix-pagination">';
        html += '<button onclick="' + fn + '(' + (page - 1) + ')"' + (page <= 1 ? ' disabled' : '') + '>← Назад</button>';
        html += '<span style="line-height:28px;font-size:12px">стр. ' + page + '</span>';
        html += '<button onclick="' + fn + '(' + (page + 1) + ')"' + (count < limit ? ' disabled' : '') + '>Вперёд →</button>';
        html += '</div>';
        return html;
    }

    // ── Публичный API ────────────────────────────────────────────────────────

    return {
        init:           init,
        openReplenish:  openReplenish,
        openChargeBonus: openChargeBonus,
        closeForm:      closeForm,
        doReplenish:    doReplenish,
        doChargeBonus:  doChargeBonus,
        syncPatient:    syncPatient,
        cancelRecord:   cancelRecord,
        loadRecords:    renderRecords,
        onDoctorChange: onDoctorChange,
        onProcChange:   onProcChange,
        onDateChange:   onDateChange,
        selectSlot:     selectSlot,
        doCreateRecord: doCreateRecord,
        loadTurnouts:   renderTurnouts,
        loadReceipts:   renderReceipts,
    };
})();

(function () {
    'use strict';

    var AJAX_URL = '/local/modules/matrix.integration/admin/ajax.php';
    var SESSID   = BX.bitrix_sessid();

    /**
     * Выполняет AJAX-запрос к единому обработчику модуля.
     *
     * @param {string}   action   Название действия (test_connection, sync_catalog, ...)
     * @param {Object}   extra    Дополнительные POST-параметры
     * @param {Function} callback Функция обратного вызова (success, message)
     */
    function ajaxRequest(action, extra, callback) {
        var data = Object.assign({ action: action, sessid: SESSID }, extra || {});

        BX.ajax({
            url:      AJAX_URL,
            method:   'POST',
            data:     data,
            dataType: 'json',
            onsuccess: function (response) {
                if (response && typeof response === 'object') {
                    callback(response.success === true, response.message || '');
                } else {
                    callback(false, 'Некорректный ответ сервера');
                }
            },
            onfailure: function () {
                callback(false, 'Ошибка сети или сервера');
            }
        });
    }

    /**
     * Показывает результат операции рядом с кнопкой.
     *
     * @param {string}  elementId ID элемента для вывода
     * @param {boolean} success   Успех или ошибка
     * @param {string}  message   Текст сообщения
     */
    function showResult(elementId, success, message) {
        var el = document.getElementById(elementId);
        if (!el) return;
        el.style.color = success ? '#3c763d' : '#a94442';
        el.innerHTML   = (success ? '✓ ' : '✗ ') + BX.util.htmlspecialchars(message);
    }

    /**
     * Блокирует или разблокирует кнопку на время выполнения запроса.
     *
     * @param {string}  buttonId ID кнопки
     * @param {boolean} loading  true — заблокировать, false — разблокировать
     * @param {string}  label    Исходный текст кнопки
     */
    function setLoading(buttonId, loading, label) {
        var btn = document.getElementById(buttonId);
        if (!btn) return;
        btn.disabled  = loading;
        btn.innerHTML = loading ? 'Выполняется...' : label;
    }

    // ── Инициализация после загрузки DOM ─────────────────────────────────────

    BX.ready(function () {

        // Проверка соединения
        var btnTest = document.getElementById('matrix-test-connection');
        if (btnTest) {
            btnTest.addEventListener('click', function () {
                setLoading('matrix-test-connection', true, 'Проверить подключение');
                ajaxRequest('test_connection', {}, function (success, message) {
                    setLoading('matrix-test-connection', false, 'Проверить подключение');
                    showResult('matrix-test-connection-result', success, message);
                });
            });
        }

        // Синхронизация каталога
        var btnCatalog = document.getElementById('matrix-sync-catalog');
        if (btnCatalog) {
            btnCatalog.addEventListener('click', function () {
                setLoading('matrix-sync-catalog', true, 'Синхронизировать каталог');
                ajaxRequest('sync_catalog', {}, function (success, message) {
                    setLoading('matrix-sync-catalog', false, 'Синхронизировать каталог');
                    showResult('matrix-sync-catalog-result', success, message);
                });
            });
        }

        // Синхронизация процедур
        var btnProcedures = document.getElementById('matrix-sync-procedures');
        if (btnProcedures) {
            btnProcedures.addEventListener('click', function () {
                setLoading('matrix-sync-procedures', true, 'Синхронизировать процедуры');
                ajaxRequest('sync_procedures', {}, function (success, message) {
                    setLoading('matrix-sync-procedures', false, 'Синхронизировать процедуры');
                    showResult('matrix-sync-procedures-result', success, message);

                    if (success) {
                        var countEl = document.getElementById('matrix-procedure-count');
                        if (countEl) countEl.innerHTML = '...обновите страницу';
                    }
                });
            });
        }

        // Синхронизация врачей
        var btnDoctors = document.getElementById('matrix-sync-doctors');
        if (btnDoctors) {
            btnDoctors.addEventListener('click', function () {
                setLoading('matrix-sync-doctors', true, 'Синхронизировать врачей');
                ajaxRequest('sync_doctors', {}, function (success, message) {
                    setLoading('matrix-sync-doctors', false, 'Синхронизировать врачей');
                    showResult('matrix-sync-doctors-result', success, message);
                });
            });
        }

        // ── Агенты: ручной запуск ────────────────────────────────────────────

        [
            { id: 'matrix-run-zero-stock', action: 'check_zero_stock',       label: 'Запустить' },
            { id: 'matrix-run-turnouts',   action: 'process_turnouts',        label: 'Запустить' },
            { id: 'matrix-run-expiring',   action: 'check_expiring_bonuses',  label: 'Запустить' },
        ].forEach(function (cfg) {
            var btn = document.getElementById(cfg.id);
            if (!btn) return;
            btn.addEventListener('click', function () {
                setLoading(cfg.id, true, cfg.label);
                ajaxRequest(cfg.action, {}, function (success, message) {
                    setLoading(cfg.id, false, cfg.label);
                    showResult(cfg.id + '-result', success, message);
                });
            });
        });

        var btnRegAgents = document.getElementById('matrix-register-agents');
        if (btnRegAgents) {
            btnRegAgents.addEventListener('click', function () {
                setLoading('matrix-register-agents', true, 'Перерегистрировать агентов');
                ajaxRequest('register_agents', {}, function (success, message) {
                    setLoading('matrix-register-agents', false, 'Перерегистрировать агентов');
                    showResult('matrix-register-agents-result', success, message);
                    if (success) { setTimeout(function () { location.reload(); }, 800); }
                });
            });
        }

        // ── Маппинг складов: добавление строки ───────────────────────────────

        var btnAddStore = document.getElementById('matrix-store-map-add');
        if (btnAddStore) {
            btnAddStore.addEventListener('click', function () {
                var tpl  = document.getElementById('matrix-store-map-row-tpl');
                var body = document.getElementById('matrix-store-map-body');
                if (!tpl || !body) return;

                var tmp = document.createElement('tbody');
                tmp.innerHTML = tpl.innerHTML;
                body.appendChild(tmp.firstElementChild);
                bindRemoveButtons();
            });
        }

        // Загрузка складов из Matrix API
        var btnLoadStores = document.getElementById('matrix-load-stores');
        if (btnLoadStores) {
            btnLoadStores.addEventListener('click', function () {
                setLoading('matrix-load-stores', true, '↺ Загрузить склады из Matrix');
                BX.ajax({
                    url:      AJAX_URL,
                    method:   'POST',
                    data:     { action: 'get_stores', sessid: SESSID },
                    dataType: 'json',
                    onsuccess: function (response) {
                        setLoading('matrix-load-stores', false, '↺ Загрузить склады из Matrix');
                        if (!response || !response.success || !response.items) {
                            showResult('matrix-load-stores-result', false, response ? (response.message || 'Ошибка загрузки') : 'Ошибка сети');
                            return;
                        }
                        replaceStoreInputsWithSelects(response.items);
                        showResult('matrix-load-stores-result', true, 'Загружено: ' + response.items.length + ' складов');
                    },
                    onfailure: function () {
                        setLoading('matrix-load-stores', false, '↺ Загрузить склады из Matrix');
                        showResult('matrix-load-stores-result', false, 'Ошибка сети');
                    }
                });
            });
        }

        bindRemoveButtons();
    });

    /**
     * Создаёт <select> из списка складов Matrix.
     */
    function buildMatrixStoreSelect(stores, currentValue) {
        var select = document.createElement('select');
        select.name = 'MATRIX_STORE_MAP_MATRIX[]';
        var blank = document.createElement('option');
        blank.value = '0';
        blank.textContent = '— Склад Matrix —';
        select.appendChild(blank);
        stores.forEach(function (s) {
            var opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = '[' + s.id + '] ' + BX.util.htmlspecialchars(s.name);
            if (parseInt(currentValue, 10) === s.id) { opt.selected = true; }
            select.appendChild(opt);
        });
        return select;
    }

    /**
     * Заменяет текстовые поля ID склада Matrix на <select> с именами.
     * Также обновляет шаблон для новых строк.
     */
    function replaceStoreInputsWithSelects(stores) {
        document.querySelectorAll('.matrix-store-map-row').forEach(function (row) {
            var input = row.querySelector('input[name="MATRIX_STORE_MAP_MATRIX[]"]');
            if (!input) { return; }
            var select = buildMatrixStoreSelect(stores, input.value || '0');
            input.parentNode.replaceChild(select, input);
        });

        // Обновляем шаблон, чтобы новые строки тоже использовали <select>
        var tpl = document.getElementById('matrix-store-map-row-tpl');
        if (tpl) {
            var wrap = document.createElement('table');
            wrap.innerHTML = '<tbody>' + tpl.innerHTML + '</tbody>';
            var input = wrap.querySelector('input[name="MATRIX_STORE_MAP_MATRIX[]"]');
            if (input) {
                var select = buildMatrixStoreSelect(stores, '0');
                input.parentNode.replaceChild(select, input);
                tpl.innerHTML = wrap.querySelector('tbody').innerHTML;
            }
        }
    }

    /**
     * Вешает обработчики удаления на все кнопки строк маппинга складов.
     */
    function bindRemoveButtons() {
        document.querySelectorAll('.matrix-store-map-remove').forEach(function (btn) {
            btn.onclick = function () {
                var row = btn.closest('tr');
                if (row) { row.remove(); }
            };
        });
    }

})();
<?php

use Bitrix\Main\Config\Option;
use Bitrix\Crm\ContactTable;
use Bitrix\Main\Loader;

$patientCount = 0;
if (Loader::includeModule('crm')) {
    $patientCount = ContactTable::getCount(['!UF_MATRIX_PATIENT_ID' => false]);
}

$lastSync  = Option::get('matrix.integration', 'MATRIX_PATIENTS_LAST_SYNC', '');
$ajaxUrl   = '/local/modules/matrix.integration/admin/ajax.php';

$resumeRaw = Option::get('matrix.integration', 'MATRIX_PATIENTS_SYNC_RESUME', '');
$resume    = ($resumeRaw !== '') ? json_decode($resumeRaw, true) : null;
?>

<tr class="heading">
    <td colspan="2">Пациенты Matrix CRM → Контакты Битрикс24</td>
</tr>

<tr>
    <td width="40%">Контактов с Matrix ID:</td>
    <td width="60%"><strong><?= $patientCount ?></strong></td>
</tr>

<tr>
    <td>Последняя полная синхронизация:</td>
    <td><strong><?= htmlspecialcharsbx($lastSync ?: 'Не выполнялась') ?></strong></td>
</tr>

<?php if ($resume && !empty($resume['next_id'])): ?>
<tr>
    <td>Незавершённая синхронизация:</td>
    <td>
        <strong style="color:#e67e22;">
            Остановлена на ID <?= (int)$resume['next_id'] ?>
            <?= !empty($resume['saved_at']) ? '(сохранено ' . htmlspecialcharsbx($resume['saved_at']) . ')' : '' ?>
        </strong>
    </td>
</tr>
<?php endif; ?>

<tr>
    <td colspan="2">
        <small>
            Перебирает пациентов из Matrix CRM начиная с ID=1.
            Останавливается после 50 последовательных ответов 404 или по достижении максимального ID
            (<em>MATRIX_PATIENTS_MAX_ID</em>, по умолчанию 10&nbsp;000).
            Синхронизация разбита на батчи по 20 ID — позиция сохраняется после каждого батча,
            поэтому можно возобновить с места остановки.
        </small>
    </td>
</tr>

<tr>
    <td colspan="2" style="padding-top:8px;">
        <label style="margin-right:16px;">
            ID&nbsp;с:&nbsp;<input type="text" id="mx-pat-from-id" value="" placeholder="1"
                style="width:80px; text-align:center;">
        </label>
        <label>
            ID&nbsp;до:&nbsp;<input type="text" id="mx-pat-to-id" value="" placeholder="без лимита"
                style="width:100px; text-align:center;">
        </label>
        <small style="margin-left:12px; color:#888;">Пустое поле — без ограничения</small>
    </td>
</tr>

<tr>
    <td colspan="2" style="padding-top:8px; display:flex; gap:8px; flex-wrap:wrap;">
        <button type="button" class="adm-btn" id="mx-pat-start-btn">
            Запустить с начала
        </button>
        <?php if ($resume && !empty($resume['next_id'])): ?>
        <button type="button" class="adm-btn adm-btn-green" id="mx-pat-resume-btn"
                data-next-id="<?= (int)$resume['next_id'] ?>"
                data-empty-count="<?= (int)($resume['empty_count'] ?? 0) ?>">
            Продолжить с ID <?= (int)$resume['next_id'] ?>
        </button>
        <?php endif; ?>
        <button type="button" class="adm-btn adm-btn-red" id="mx-pat-stop-btn" style="display:none;">
            Остановить
        </button>
    </td>
</tr>

<tr>
    <td colspan="2" id="mx-pat-progress" style="display:none; padding-top:10px;">

        <div style="background:#e9e9e9; border-radius:4px; height:22px; width:420px; max-width:100%; position:relative; overflow:hidden;">
            <div id="mx-pat-bar"
                 style="background:#5cb85c; height:22px; width:0%; transition:width 0.4s;"></div>
            <span id="mx-pat-pct"
                  style="position:absolute; left:50%; top:2px; transform:translateX(-50%); font-size:12px; font-weight:bold; color:#333;">
                0%
            </span>
        </div>

        <div style="margin-top:8px; display:flex; gap:20px; font-size:13px;">
            <span>Создано:&nbsp;<strong id="mx-pat-created">0</strong></span>
            <span>Обновлено:&nbsp;<strong id="mx-pat-updated">0</strong></span>
            <span>Пропущено:&nbsp;<strong id="mx-pat-skipped">0</strong></span>
            <span>Всего:&nbsp;<strong id="mx-pat-total">0</strong></span>
        </div>

        <div id="mx-pat-status" style="margin-top:6px; font-size:12px; color:#555;"></div>
        <div id="mx-pat-errors" style="margin-top:6px; font-size:12px; color:#c0392b; display:none;"></div>

    </td>
</tr>

<script>
(function () {
    var AJAX_URL   = <?= json_encode($ajaxUrl) ?>;
    var BATCH_SIZE = 20;

    var stopped    = false;
    var maxId      = 10000;  // обновляется из ответа сервера
    var syncFromId = 1;      // стартовый ID текущего запуска (для прогресс-бара)
    var syncToId   = 0;      // верхний ID текущего запуска (0 = без ограничения)
    var totals     = { created: 0, updated: 0, skipped: 0, total: 0 };
    var allErrors  = [];

    function el(id) { return document.getElementById(id); }

    /** Читает поле «ID с» из формы, возвращает int >= 1. */
    function getInputFromId() {
        var v = parseInt(el('mx-pat-from-id').value, 10);
        return v > 0 ? v : 1;
    }

    /** Читает поле «ID до» из формы, возвращает int > 0 или 0 если не задано. */
    function getInputToId() {
        var v = parseInt(el('mx-pat-to-id').value, 10);
        return v > 0 ? v : 0;
    }

    function updateProgress(nextId) {
        var rangeTo   = syncToId > 0 ? syncToId : maxId;
        var rangeSize = rangeTo - syncFromId + 1;
        var pct = rangeSize > 0 ? Math.min(100, Math.round((nextId - syncFromId) / rangeSize * 100)) : 0;
        el('mx-pat-bar').style.width = pct + '%';
        el('mx-pat-pct').textContent = pct + '%';
    }

    function updateStats() {
        el('mx-pat-created').textContent = totals.created;
        el('mx-pat-updated').textContent = totals.updated;
        el('mx-pat-skipped').textContent = totals.skipped;
        el('mx-pat-total').textContent   = totals.total;
    }

    function showErrors() {
        if (!allErrors.length) { return; }
        var errEl = el('mx-pat-errors');
        errEl.style.display = 'block';
        errEl.textContent   = 'Ошибки: ' + allErrors.slice(0, 5).join('; ') +
            (allErrors.length > 5 ? ' ... (ещё ' + (allErrors.length - 5) + ')' : '');
    }

    function setRunning(running) {
        var startBtn  = el('mx-pat-start-btn');
        var resumeBtn = el('mx-pat-resume-btn');
        var stopBtn   = el('mx-pat-stop-btn');

        startBtn.disabled = running;
        if (resumeBtn) { resumeBtn.disabled = running; }
        stopBtn.style.display = running ? 'inline-block' : 'none';
        stopBtn.disabled      = false;
        if (!running) {
            startBtn.textContent = 'Запустить с начала';
        }
    }

    function finish(message) {
        el('mx-pat-bar').style.width    = '100%';
        el('mx-pat-pct').textContent    = '100%';
        el('mx-pat-status').textContent = message;
        setRunning(false);
        showErrors();
    }

    function runBatch(startId, emptyCount) {
        if (stopped) {
            finish('Остановлено пользователем. Позиция сохранена — можно продолжить позже.');
            return;
        }

        var effectiveMax = syncToId > 0 ? syncToId : maxId;
        var endPreview   = Math.min(startId + BATCH_SIZE - 1, effectiveMax);
        el('mx-pat-status').textContent = 'Обработка ID ' + startId + '–' + endPreview + '...';

        var body = new URLSearchParams({
            sessid:      BX.bitrix_sessid(),
            action:      'sync_patients_batch',
            start_id:    startId,
            batch_size:  BATCH_SIZE,
            empty_count: emptyCount,
            to_id:       syncToId
        });

        fetch(AJAX_URL, { method: 'POST', body: body })
            .then(function (r) {
                if (!r.ok) { throw new Error('HTTP ' + r.status); }
                return r.json();
            })
            .then(function (resp) {
                if (!resp.success) {
                    finish('Ошибка сервера: ' + (resp.message || 'неизвестная'));
                    return;
                }

                var d = resp.data;
                // Обновляем maxId из ответа только если явный to_id не задан
                if (d.max_id && syncToId === 0) { maxId = d.max_id; }

                totals.created += d.created;
                totals.updated += d.updated;
                totals.skipped += d.skipped;
                totals.total   += d.total;
                allErrors       = allErrors.concat(d.errors || []);

                updateProgress(d.next_id);
                updateStats();

                if (d.finished || stopped) {
                    finish(d.finished
                        ? 'Синхронизация завершена.'
                        : 'Остановлено. Позиция сохранена — можно продолжить позже.');
                } else {
                    runBatch(d.next_id, d.empty_count);
                }
            })
            .catch(function (e) {
                finish('Ошибка: ' + (e.message || e) + '. Позиция могла сохраниться — обновите страницу.');
            });
    }

    function startSync(fromIdParam, fromEmptyCount) {
        stopped    = false;
        syncFromId = fromIdParam;
        syncToId   = getInputToId();
        totals     = { created: 0, updated: 0, skipped: 0, total: 0 };
        allErrors  = [];

        el('mx-pat-progress').style.display = 'block';
        el('mx-pat-errors').style.display   = 'none';
        el('mx-pat-errors').textContent     = '';
        el('mx-pat-bar').style.width        = '0%';
        el('mx-pat-pct').textContent        = '0%';
        el('mx-pat-status').textContent     = 'Запуск...';
        setRunning(true);
        updateStats();
        updateProgress(fromIdParam);

        runBatch(fromIdParam, fromEmptyCount);
    }

    BX.ready(function () {
        // «Запустить с начала» — читает поля диапазона
        el('mx-pat-start-btn').addEventListener('click', function () {
            startSync(getInputFromId(), 0);
        });

        // «Продолжить» — продолжает с сохранённой позиции; to_id читается из поля
        var resumeBtn = el('mx-pat-resume-btn');
        if (resumeBtn) {
            resumeBtn.addEventListener('click', function () {
                var nextId     = parseInt(this.dataset.nextId, 10)     || 1;
                var emptyCount = parseInt(this.dataset.emptyCount, 10) || 0;
                startSync(nextId, emptyCount);
            });
        }

        el('mx-pat-stop-btn').addEventListener('click', function () {
            stopped = true;
            this.disabled = true;
            el('mx-pat-status').textContent = 'Остановка после текущего батча...';
        });
    });
}());
</script>

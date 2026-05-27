<?php

use Matrix\Integration\Orm\ProcedureTable;

// Считаем количество процедур в ORM
$procedureCount = 0;
try {
    $procedureCount = ProcedureTable::getCount();
} catch (\Exception $e) {
    // таблица ещё не создана
}
?>

<tr class="heading">
    <td colspan="2">Процедуры Matrix CRM</td>
</tr>

<tr>
    <td width="40%">Процедур в базе Битрикс24:</td>
    <td width="60%">
        <strong id="matrix-procedure-count"><?= $procedureCount ?></strong>
    </td>
</tr>

<tr>
    <td colspan="2">
        <button type="button" class="adm-btn" id="matrix-sync-procedures">
            Синхронизировать процедуры
        </button>
        <span id="matrix-sync-procedures-result" style="margin-left:10px;"></span>
        <br><small style="margin-top:4px; display:block;">
            Загружает список процедур из Matrix CRM и сохраняет в локальную таблицу
            с полем длительности (duration). Используется при создании записей на приём.
        </small>
    </td>
</tr>
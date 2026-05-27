<?php

// Считаем количество сотрудников с заполненным UF_MATRIX_DOCTOR_ID
$doctorCount = 0;
$userResult  = \CUser::GetList(
    ($by = 'id'),
    ($order = 'asc'),
    ['!UF_MATRIX_DOCTOR_ID' => false],
    ['SELECT' => ['ID'], 'NAV_PARAMS' => ['nTopCount' => 1, 'bDescPageNumbering' => false]]
);
// Получаем общее количество
$doctorCountResult = \CUser::GetList(
    ($by = 'id'),
    ($order = 'asc'),
    ['!UF_MATRIX_DOCTOR_ID' => false],
    ['SELECT' => ['ID']]
);
while ($doctorCountResult->Fetch()) {
    $doctorCount++;
}
?>

<tr class="heading">
    <td colspan="2">Врачи Matrix CRM → Сотрудники Битрикс24</td>
</tr>

<tr>
    <td width="40%">Сотрудников с Matrix ID:</td>
    <td width="60%">
        <strong><?= $doctorCount ?></strong>
    </td>
</tr>

<tr>
    <td colspan="2">
        <small>
            Синхронизация сопоставляет врачей из Matrix CRM с сотрудниками Битрикс24 по ФИО.
            При совпадении — заполняет поле UF_MATRIX_DOCTOR_ID.
            При отсутствии — создаёт нового сотрудника.
        </small>
    </td>
</tr>

<tr>
    <td colspan="2" style="padding-top: 10px;">
        <button type="button" class="adm-btn" id="matrix-sync-doctors">
            Синхронизировать врачей
        </button>
        <span id="matrix-sync-doctors-result" style="margin-left:10px;"></span>
    </td>
</tr>
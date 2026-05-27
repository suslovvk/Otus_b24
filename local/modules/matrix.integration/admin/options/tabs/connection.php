<?php

use Bitrix\Main\Config\Option;

const MODULE_ID_TAB = 'matrix.integration';

$baseUrl   = Option::get(MODULE_ID_TAB, 'MATRIX_BASE_URL', '');
$apiKey    = Option::get(MODULE_ID_TAB, 'MATRIX_API_KEY', '');
$debugMode = Option::get(MODULE_ID_TAB, 'MATRIX_DEBUG_MODE', 'N');
?>

<?php if ($request->getQuery('saved') === 'Y'): ?>
    <tr>
        <td colspan="2">
            <div class="adm-info-message adm-info-message-green">Настройки сохранены.</div>
        </td>
    </tr>
<?php endif; ?>

<tr class="heading">
    <td colspan="2">Подключение к Matrix CRM API</td>
</tr>

<tr>
    <td width="40%"><label for="MATRIX_BASE_URL">Base URL:</label></td>
    <td width="60%">
        <input type="text"
               id="MATRIX_BASE_URL"
               name="MATRIX_BASE_URL"
               value="<?= htmlspecialcharsbx($baseUrl) ?>"
               size="60"
               placeholder="https://domain.matrixcrm.ru/api/v1/"/>
        <br><small>Базовый URL API Matrix CRM. Пример: https://domain.matrixcrm.ru/api/v1/</small>
    </td>
</tr>

<tr>
    <td><label for="MATRIX_API_KEY">API Key:</label></td>
    <td>
        <input type="password"
               id="MATRIX_API_KEY"
               name="MATRIX_API_KEY"
               value="<?= htmlspecialcharsbx($apiKey) ?>"
               size="60"
               placeholder="Ключ доступа к Matrix CRM"/>
    </td>
</tr>

<tr>
    <td><label for="MATRIX_DEBUG_MODE">Режим отладки:</label></td>
    <td>
        <input type="checkbox"
               id="MATRIX_DEBUG_MODE"
               name="MATRIX_DEBUG_MODE"
               value="Y"
            <?= $debugMode === 'Y' ? 'checked' : '' ?>/>
        <label for="MATRIX_DEBUG_MODE">Писать все запросы в лог</label>
    </td>
</tr>

<tr class="heading">
    <td colspan="2">Проверка подключения</td>
</tr>

<tr>
    <td colspan="2">
        <button type="button"
                class="adm-btn"
                id="matrix-test-connection">Проверить подключение</button>
        <span id="matrix-test-connection-result" style="margin-left: 10px;"></span>
    </td>
</tr>
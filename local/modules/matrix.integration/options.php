<?php

define('STOP_STATISTICS', true);
define('NO_KEEP_STATISTIC', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

const MODULE_ID = 'matrix.integration';

global $APPLICATION, $USER;

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm('Access denied');
}

Loader::includeModule(MODULE_ID);

$request = \Bitrix\Main\Context::getCurrent()->getRequest();

// ── Сохранение настроек ──────────────────────────────────────────────────────

if (
    $request->isPost()
    && check_bitrix_sessid()
    && $request->getPost('save')
) {
    Option::set(MODULE_ID, 'MATRIX_BASE_URL',          trim((string) $request->getPost('MATRIX_BASE_URL')));
    Option::set(MODULE_ID, 'MATRIX_API_KEY',           trim((string) $request->getPost('MATRIX_API_KEY')));
    Option::set(MODULE_ID, 'MATRIX_CATALOG_IBLOCK_ID', trim((string) $request->getPost('MATRIX_CATALOG_IBLOCK_ID')));
    Option::set(MODULE_ID, 'MATRIX_DEBUG_MODE',        $request->getPost('MATRIX_DEBUG_MODE') === 'Y' ? 'Y' : 'N');
    Option::set(MODULE_ID, 'MATRIX_DOCTOR_GROUP_IDS',  trim((string) $request->getPost('MATRIX_DOCTOR_GROUP_IDS')));

    $matrixStores = $request->getPost('MATRIX_STORE_MAP_MATRIX') ?? [];
    $bitrixStores = $request->getPost('MATRIX_STORE_MAP_BITRIX') ?? [];
    $storeMap     = [];

    if (is_array($matrixStores)) {
        foreach ($matrixStores as $i => $matrixStoreId) {
            $bitrixStoreId = (int) ($bitrixStores[$i] ?? 0);
            if ((int) $matrixStoreId > 0 && $bitrixStoreId > 0) {
                $storeMap[(int) $matrixStoreId] = $bitrixStoreId;
            }
        }
    }

    Option::set(MODULE_ID, 'MATRIX_STORE_MAP', json_encode($storeMap));

    LocalRedirect(
        $APPLICATION->GetCurPage()
        . '?mid=' . MODULE_ID
        . '&lang=' . LANGUAGE_ID
        . '&saved=Y'
    );
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$aTabs = [
    ['DIV' => 'connection', 'TAB' => 'Подключение',  'TITLE' => 'Настройки подключения Matrix'],
    ['DIV' => 'catalog',    'TAB' => 'Каталог',       'TITLE' => 'Синхронизация каталога'],
    ['DIV' => 'procedures', 'TAB' => 'Процедуры',     'TITLE' => 'Синхронизация процедур'],
    ['DIV' => 'doctors',    'TAB' => 'Врачи',         'TITLE' => 'Синхронизация врачей'],
    ['DIV' => 'patients',   'TAB' => 'Пациенты',      'TITLE' => 'Синхронизация пациентов'],
    ['DIV' => 'agents',     'TAB' => 'Агенты',        'TITLE' => 'Фоновые агенты синхронизации'],
];

$tabControl = new CAdminTabControl('tabControl', $aTabs);
$tabControl->Begin();
?>

<form method="POST"
      action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= MODULE_ID ?>&lang=<?= LANGUAGE_ID ?>">

    <?= bitrix_sessid_post() ?>

    <?php
    $tabControl->BeginNextTab();
    require __DIR__ . '/admin/options/tabs/connection.php';

    $tabControl->BeginNextTab();
    require __DIR__ . '/admin/options/tabs/catalog.php';

    $tabControl->BeginNextTab();
    require __DIR__ . '/admin/options/tabs/procedures.php';

    $tabControl->BeginNextTab();
    require __DIR__ . '/admin/options/tabs/doctors.php';

    $tabControl->BeginNextTab();
    require __DIR__ . '/admin/options/tabs/patients.php';

    $tabControl->BeginNextTab();
    require __DIR__ . '/admin/options/tabs/agents.php';
    ?>

    <?php $tabControl->Buttons(); ?>

    <input type="submit" name="save" value="Сохранить" class="adm-btn-save">

</form>

<script src="/local/modules/matrix.integration/admin/options/assets/options.js"></script>

<?php
$tabControl->End();
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
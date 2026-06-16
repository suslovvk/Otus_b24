<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Otus\Dz11\FieldInstaller;

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NO_AGENT_CHECK', true);
define('STOP_STATISTICS', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

Loc::loadMessages(__FILE__);

if (!$USER->IsAdmin()) {
    die(Loc::getMessage('DZ11_FIELD_ACCESS_DENIED'));
}

if (!Loader::includeModule('crm')) {
    die(Loc::getMessage('DZ11_FIELD_CRM_NOT_LOADED'));
}

require_once __DIR__ . '/lib/FieldInstaller.php';

$installer = new FieldInstaller();
$result    = $installer->install();

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$statusClass = $result['success'] ? 'adm-info-message-green' : 'adm-info-message';
?>
<div class="adm-info-message-wrap">
    <div class="<?= htmlspecialchars($statusClass) ?>">
        <?= htmlspecialchars($result['message']) ?>
    </div>
</div>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

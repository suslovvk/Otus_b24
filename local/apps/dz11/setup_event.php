<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NO_AGENT_CHECK', true);
define('STOP_STATISTICS', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

Loc::loadMessages(__FILE__);

if (!$USER->IsAdmin()) {
    die(Loc::getMessage('DZ11_SETUP_ACCESS_DENIED'));
}

if (!Loader::includeModule('rest')) {
    die(Loc::getMessage('DZ11_SETUP_REST_NOT_LOADED'));
}

require_once __DIR__ . '/settings.php';

$handlerUrl = 'http://otus.dev.sunweb.pro/local/apps/dz11/handler.php';
$eventName  = 'ONCRM_TIMELINE_COMMENT_ADD';

$appRow = \Bitrix\Rest\AppTable::getList([
    'filter' => ['=CODE' => C_REST_CLIENT_ID],
    'select' => ['ID'],
])->fetch();
$appId = $appRow ? (int)$appRow['ID'] : 0;

if ($appId <= 0) {
    die(Loc::getMessage('DZ11_SETUP_REST_NOT_LOADED'));
}

$result  = '';
$success = false;

if (isset($_POST['bind'])) {
    check_bitrix_sessid();

    $existing = \Bitrix\Rest\EventTable::getList([
        'filter' => [
            '=APP_ID'        => $appId,
            '=EVENT_NAME'    => $eventName,
            '=EVENT_HANDLER' => $handlerUrl,
        ],
        'select' => ['ID'],
    ])->fetch();

    if ($existing) {
        $success = true;
        $result  = Loc::getMessage('DZ11_SETUP_ALREADY_EXISTS', ['#ID#' => $existing['ID']]);
    } else {
        $addResult = \Bitrix\Rest\EventTable::add([
            'APP_ID'        => $appId,
            'EVENT_NAME'    => $eventName,
            'EVENT_HANDLER' => $handlerUrl,
            'USER_ID'       => $USER->GetID(),
        ]);

        if ($addResult->isSuccess()) {
            $success = true;
            $result  = Loc::getMessage('DZ11_SETUP_SUCCESS', ['#ID#' => $addResult->getId()]);
        } else {
            $result = Loc::getMessage('DZ11_SETUP_ERROR', [
                '#ERROR#' => implode(', ', $addResult->getErrorMessages()),
            ]);
        }
    }
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>
<div class="adm-info-message-wrap" style="max-width:600px; margin:30px auto;">
    <h2><?= Loc::getMessage('DZ11_SETUP_TITLE') ?></h2>
    <table class="adm-detail-content-table" style="width:100%; margin-bottom:20px;">
        <tr><td style="width:40%"><b><?= Loc::getMessage('DZ11_SETUP_APP_ID_LABEL') ?></b></td><td><?= (int)$appId ?></td></tr>
        <tr><td><b><?= Loc::getMessage('DZ11_SETUP_EVENT_LABEL') ?></b></td><td><?= htmlspecialchars($eventName) ?></td></tr>
        <tr><td><b><?= Loc::getMessage('DZ11_SETUP_HANDLER_LABEL') ?></b></td><td><?= htmlspecialchars($handlerUrl) ?></td></tr>
    </table>

    <?php if ($result): ?>
        <div class="<?= $success ? 'adm-info-message-green' : 'adm-info-message' ?>">
            <?= htmlspecialchars($result) ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <?= bitrix_sessid_post() ?>
        <input type="submit" name="bind" value="<?= Loc::getMessage('DZ11_SETUP_BTN_SUBMIT') ?>"
               class="adm-btn-save" style="margin-top:15px;">
    </form>
</div>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

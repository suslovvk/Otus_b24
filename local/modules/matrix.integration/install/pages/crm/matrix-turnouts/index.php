<?php

use Bitrix\Main\Loader;

define('STOP_STATISTICS', true);
define('NO_KEEP_STATISTIC', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

global $APPLICATION, $USER;

if (
    !$USER->IsAuthorized()
    || (!$USER->IsAdmin() && !$USER->CanDoOperation('crm'))
    || !Loader::includeModule('matrix.integration')
    || !Loader::includeModule('crm')
) {
    LocalRedirect(SITE_DIR . 'auth/?backurl=' . urlencode($APPLICATION->GetCurPage()));
    die();
}

$APPLICATION->SetTitle('Контрольные явки');

$APPLICATION->IncludeComponent('matrix:crm.patient.turnouts', '.default', [
    'ENTITY_ID'      => 0,
    'ENTITY_TYPE_ID' => 0,
], false);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';

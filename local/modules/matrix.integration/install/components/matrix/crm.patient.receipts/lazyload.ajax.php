<?php

use Bitrix\Main\Application;
use Bitrix\Main\Loader;

define('NO_KEEP_STATISTIC', 'Y');
define('NO_AGENT_STATISTIC', 'Y');
define('NO_AGENT_CHECK', true);
define('PUBLIC_AJAX_MODE', true);
define('DisableEventsCheck', true);

$siteId = isset($_REQUEST['site']) ? mb_substr(preg_replace('/[^a-z0-9_]/i', '', $_REQUEST['site']), 0, 2) : '';
if ($siteId !== '') {
    define('SITE_ID', $siteId);
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

if (!check_bitrix_sessid()) {
    die();
}

Header('Content-Type: text/html; charset=' . LANG_CHARSET);

Loader::includeModule('crm');
Loader::includeModule('matrix.integration');

global $APPLICATION;
$APPLICATION->ShowAjaxHead();

$request       = Application::getInstance()->getContext()->getRequest();
$componentData = $request->get('PARAMS');

$componentParams = [];
if (is_array($componentData) && isset($componentData['params']) && is_array($componentData['params'])) {
    $componentParams = $componentData['params'];
}

$componentParams['AJAX_LOADER'] = [
    'url'      => $request->getServer()->get('REQUEST_URI'),
    'method'   => 'POST',
    'dataType' => 'ajax',
    'data'     => ['PARAMS' => $componentData],
];

$APPLICATION->IncludeComponent('matrix:crm.patient.receipts', '', $componentParams);

\CMain::FinalActions();

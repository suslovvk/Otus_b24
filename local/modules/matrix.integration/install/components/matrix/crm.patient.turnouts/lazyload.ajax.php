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

// Patch REQUEST_URI for tab mode so grid.baseUrl carries ENTITY_ID across sort/page/settings requests
if (strpos($_SERVER['REQUEST_URI'], 'ENTITY_ID=') === false) {
    $earlyEntityId = 0;
    if (!empty($_GET['ENTITY_ID'])) {
        $earlyEntityId = (int) $_GET['ENTITY_ID'];
    } elseif (!empty($_POST['PARAMS']['params']['ENTITY_ID'])) {
        $earlyEntityId = (int) $_POST['PARAMS']['params']['ENTITY_ID'];
    }
    if ($earlyEntityId > 0) {
        $sep = str_contains($_SERVER['REQUEST_URI'], '?') ? '&' : '?';
        $_SERVER['REQUEST_URI'] .= $sep . 'ENTITY_ID=' . $earlyEntityId . '&ENTITY_TYPE_ID=3';
    }
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

$request = Application::getInstance()->getContext()->getRequest();

$componentData = $request->get('PARAMS');
if (!is_array($componentData)) {
    $componentData = $request->getPost('PARAMS');
}

$componentParams = [];
if (is_array($componentData) && isset($componentData['params']) && is_array($componentData['params'])) {
    $componentParams = $componentData['params'];
}

if (empty($componentParams['ENTITY_ID'])) {
    $directId = (int) ($request->get('ENTITY_ID') ?: $request->getPost('ENTITY_ID'));
    if ($directId > 0) {
        $componentParams['ENTITY_ID']      = $directId;
        $componentParams['ENTITY_TYPE_ID'] = 3;
    }
}

$componentParams['AJAX_LOADER'] = [
    'url'      => $request->getServer()->get('REQUEST_URI'),
    'method'   => 'POST',
    'dataType' => 'ajax',
    'data'     => ['PARAMS' => $componentData],
];

$APPLICATION->IncludeComponent('matrix:crm.patient.turnouts', '', $componentParams);

\CMain::FinalActions();

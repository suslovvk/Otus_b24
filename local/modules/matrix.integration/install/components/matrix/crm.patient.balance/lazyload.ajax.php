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

// Resolve ENTITY_ID BEFORE prolog so the Bitrix HttpRequest object (built
// during prolog from $_SERVER) sees a REQUEST_URI that includes ENTITY_ID.
// This makes main.ui.grid use a base AJAX URL that carries ENTITY_ID, so
// every subsequent sort/paginate request automatically preserves it.
if (strpos($_SERVER['REQUEST_URI'], 'ENTITY_ID=') === false) {
    $earlyEntityId = 0;
    if (!empty($_GET['ENTITY_ID'])) {
        // Grid AJAX requests (sort, paginate) pass ENTITY_ID as a GET param
        $earlyEntityId = (int) $_GET['ENTITY_ID'];
    } elseif (!empty($_POST['PARAMS']['params']['ENTITY_ID'])) {
        // CRM tab initial lazyload sends ENTITY_ID nested in POST PARAMS
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

// Read PARAMS from whichever transport was used
$componentData = $request->get('PARAMS');
if (!is_array($componentData)) {
    $componentData = $request->getPost('PARAMS');
}

$componentParams = [];
if (is_array($componentData) && isset($componentData['params']) && is_array($componentData['params'])) {
    $componentParams = $componentData['params'];
}

// Fallback: read ENTITY_ID directly from GET (grid sort/paginate requests)
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

$APPLICATION->IncludeComponent('matrix:crm.patient.balance', '', $componentParams);

\CMain::FinalActions();

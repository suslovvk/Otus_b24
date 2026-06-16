<?php

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NO_AGENT_CHECK',     true);
define('STOP_STATISTICS',    true);
define('NOT_CHECK_PERMISSIONS', true);

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/crest.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Localization\Loc;
use Otus\Dz11\EventHandler;

Loc::loadMessages(__FILE__);

require_once __DIR__ . '/lib/EventHandler.php';

$handler = new EventHandler();
$handler->process();

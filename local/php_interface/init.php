<?php

use Bitrix\Main\EventManager;
use Local\Events\IblockCrmSync;
use Otus\Property\BookingProperty;

$path = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($path)) {
    die("Файл автозагрузчика composer не найден по пути: " . $path);
}
require $path;

$pathAutoload = __DIR__ . '/../app/autoload.php';
if (!file_exists($pathAutoload)) {
    die("Файл автозагрузчика не найден по пути: " . $pathAutoload);
}

$eventManager = EventManager::getInstance();
$eventManager->addEventHandler(
    'iblock',
    'OnIBlockPropertyBuildList',
    [BookingProperty::class, 'GetUserTypeDescription'],
);

// Двусторонняя синхронизация инфоблока «Заявки» и сделок CRM

require_once __DIR__ . '/../events/IblockCrmSync.php';

// Инфоблок → Сделка
AddEventHandler('iblock', 'OnAfterIBlockElementAdd',    [IblockCrmSync::class, 'onAfterIBlockElementAdd']);
AddEventHandler('iblock', 'OnAfterIBlockElementUpdate', [IblockCrmSync::class, 'onAfterIBlockElementUpdate']);

// Сделка → Инфоблок
// CCrmDeal::Add/Update не вызывают DealTable::add/update, поэтому используем события CRM-модуля
$eventManager->addEventHandler('crm', 'OnAfterCrmDealAdd',    [IblockCrmSync::class, 'onDealAfterAdd']);
$eventManager->addEventHandler('crm', 'OnAfterCrmDealUpdate', [IblockCrmSync::class, 'onDealAfterUpdate']);

require $pathAutoload;

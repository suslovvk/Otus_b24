<?php

use Bitrix\Main\EventManager;
use Otus\Property\BookingProperty;

$path = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($path)) {
    die("Файл автозагрузчика compose не найден по пути: " . $path);
}
require $path;

$pathAutoload = __DIR__ . '/../app/autoload.php';
if (!file_exists($pathAutoload)) {
    die("Файл автозагрузчика compose не найден по пути: " . $path);
}


/* */

$eventManager = EventManager::getInstance();
$eventManager->addEventHandler(
    'iblock',
    'OnIBlockPropertyBuildList',
    [BookingProperty::class, 'GetUserTypeDescription'],
);
require $pathAutoload;
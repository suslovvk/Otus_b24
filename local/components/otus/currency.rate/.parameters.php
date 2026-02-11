<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Currency\CurrencyTable;

// Подключаем модуль currency
if (!\Bitrix\Main\Loader::includeModule('currency')) {
    return;
}

$arCurrencies = [];
$res = CurrencyTable::getList([
    'select' => ['CURRENCY'],
    'order' => ['CURRENCY' => 'ASC'],
]);

while ($item = $res->fetch()) {
    $arCurrencies[$item['CURRENCY']] = '['.$item['CURRENCY'].']';
}

$arComponentParameters = [
    "PARAMETERS" => [
        "CURRENCY" => [
            "PARENT" => "BASE",
            "NAME" => "Валюта",
            "TYPE" => "LIST",
            "VALUES" => $arCurrencies,
            "DEFAULT" => "USD",
        ],
    ],
];

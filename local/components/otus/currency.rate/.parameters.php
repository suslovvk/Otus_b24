<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Currency\CurrencyTable;

$arCurrencies = [];
$res = CurrencyTable::getList([
    'select' => ['CURRENCY', 'NAME'],
    'order' => ['CURRENCY' => 'ASC'],
]);

while ($item = $res->fetch()) {
    $arCurrencies[$item['CURRENCY']] = $item['NAME'];
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

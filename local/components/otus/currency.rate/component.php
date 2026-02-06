<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Currency\CurrencyTable;

$currencyCode = $arParams['CURRENCY'];

$currency = CurrencyTable::getRow([
    'filter' => ['=CURRENCY' => $currencyCode],
]);

if (!$currency) {
    ShowError("Валюта не найдена");
    return;
}

$arResult = [
    'CURRENCY' => $currency['CURRENCY'],
    'NAME' => $currency['NAME'],
    'RATE' => $currency['CURRENT_BASE_RATE'],
];

$this->includeComponentTemplate();

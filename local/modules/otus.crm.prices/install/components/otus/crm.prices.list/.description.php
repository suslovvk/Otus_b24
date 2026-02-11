<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

$arComponentDescription = [
    'NAME' => Loc::getMessage('OTUS_PRICES_COMPONENT_NAME'),
    'DESCRIPTION' => Loc::getMessage('OTUS_PRICES_COMPONENT_DESCRIPTION'),
    'PATH' => [
        'ID' => 'otus',
        'NAME' => 'OTUS',
        'CHILD' => [
            'ID' => 'crm',
            'NAME' => 'CRM',
        ],
    ],
];
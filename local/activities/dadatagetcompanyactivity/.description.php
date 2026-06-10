<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arActivityDescription = [
    'NAME'        => 'Получить/создать компанию по ИНН',
    'DESCRIPTION' => 'Ищет компанию в CRM по ИНН. Если не найдена — запрашивает данные из DaData, создаёт компанию и возвращает её ID.',
    'TYPE'        => ['activity', 'robot_activity'],
    'CLASS'       => 'DadataGetCompanyActivity',
    'JSCLASS'     => 'BizProcActivity',
    'CATEGORY'    => [
        'ID' => 'other',
    ],
    'RETURN' => [
        'CompanyId' => [
            'NAME' => 'ID компании в CRM',
            'TYPE' => 'int',
        ],
        'CompanyIdFormatted' => [
            'NAME' => 'ID компании (формат CO_N для свойства CRM_ELEMENT)',
            'TYPE' => 'string',
        ],
    ],
    'PROPERTIES' => [
        'INN' => [
            'NAME'          => 'ИНН компании',
            'DESCRIPTION'   => 'ИНН для поиска или создания компании в CRM (10 цифр для юрлица, 12 для ИП)',
            'TYPE'          => 'string',
            'REQUIRED'      => 'Y',
            'MULTIPLE'      => 'N',
            'DEFAULT_VALUE' => '',
            'EDITABLE'      => 'Y',
            'FILTRABLE'     => 'N',
        ],
    ],
];

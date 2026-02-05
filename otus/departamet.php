<?php

use Otus\Orm\ClinicDepartamentsTable;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Iblock\Elements\ElementProceduresTable;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

$APPLICATION->SetTitle('Отделения клиники');

$result = ClinicDepartamentsTable::getList([
    'select' => [
        'DEPARTAMENT_ID'   => 'ID',
        'DEPARTAMENT_NAME',
        'DOCTOR_ID'       => 'DOCTOR.ID',
        'DOCTOR_NAME'     => 'DOCTOR.NAME',
        'PROCEDURE_ID'    => 'PROCEDURE.ID',
        'PROCEDURE_NAME'  => 'PROCEDURE.NAME',
    ],

    'runtime' => [
        new Reference(
            'PROCEDURE',
            ElementProceduresTable::class,
            Join::on('this.DOCTOR.PROCEDURE.VALUE', 'ref.ID')
        ),
    ],
]);

$rows = $result->fetchAll();

/**
 * Сборка иерархии
 */
$tree = [];

foreach ($rows as $row) {

    $depId = $row['DEPARTAMENT_ID'];
    $docId = $row['DOCTOR_ID'];

    $tree[$depId]['ID'] = $depId;
    $tree[$depId]['NAME'] = $row['DEPARTAMENT_NAME'];

    if (!$docId) {
        continue;
    }

    $tree[$depId]['DOCTORS'][$docId]['ID'] = $docId;
    $tree[$depId]['DOCTORS'][$docId]['NAME'] = $row['DOCTOR_NAME'];

    if ($row['PROCEDURE_ID']) {
        $tree[$depId]['DOCTORS'][$docId]['PROCEDURES'][$row['PROCEDURE_ID']] = [
            'ID'   => $row['PROCEDURE_ID'],
            'NAME' => $row['PROCEDURE_NAME'],
        ];
    }
}

dump($tree);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';

<?php

namespace Otus\Orm;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Iblock\Elements\ElementDoctorsTable;

class ClinicDepartamentsTable extends DataManager
{
    public static function getTableName()
    {
        return 'otus_clinic_departaments';
    }

    public static function getMap()
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary(true)
                ->configureAutocomplete(true),

            (new StringField('DEPARTAMENT_NAME'))
                ->configureRequired(true),

            // департамент → таблица линков
            (new Reference(
                'DOCTORS_LINK',
                ClinicDepartametsLincTable::class,
                Join::on('this.ID', 'ref.DEPARTAMENT_ID')
            )),

            // линк → врач (ИБ)
            (new Reference(
                'DOCTOR',
                ElementDoctorsTable::class,
                Join::on('this.DOCTORS_LINK.DOCTOR_ID', 'ref.ID')
            )),
        ];
    }
}

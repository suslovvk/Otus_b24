<?php

namespace Matrix\Integration\Orm;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

/**
 * ORM: matrix_doctor_procedures
 * Хранит связи врач → процедура → специальность (из /doctors API).
 */
class DoctorProcedureTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'matrix_doctor_procedures';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary(true)
                ->configureAutocomplete(true),
            (new IntegerField('MATRIX_DOCTOR_ID'))->configureRequired(true),
            (new IntegerField('MATRIX_PROCEDURE_ID'))->configureRequired(true),
            (new IntegerField('SPECIALTY_ID'))->configureRequired(true),
            (new StringField('SPECIALTY_NAME'))->configureNullable(true)->configureSize(255),
        ];
    }
}

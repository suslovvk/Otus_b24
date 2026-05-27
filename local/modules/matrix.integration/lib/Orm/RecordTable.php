<?php

namespace Matrix\Integration\Orm;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

/**
 * ORM: matrix_records
 */
class RecordTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'matrix_records';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary(true)
                ->configureAutocomplete(true),
            (new IntegerField('MATRIX_RECORD_ID'))->configureRequired(true),
            (new IntegerField('MATRIX_PATIENT_ID'))->configureRequired(true),
            (new IntegerField('BITRIX_CONTACT_ID'))->configureNullable(true),
            (new IntegerField('MATRIX_DOCTOR_ID'))->configureNullable(true),
            (new IntegerField('BITRIX_USER_ID'))->configureNullable(true),
            (new IntegerField('MATRIX_PROCEDURE_ID'))->configureNullable(true),
            (new DatetimeField('DATETIME'))->configureRequired(true),
            (new StringField('STATUS'))->configureNullable(true),
            (new StringField('DOCTOR_NAME'))->configureNullable(true),
            (new StringField('PROCEDURE_NAME'))->configureNullable(true),
            (new IntegerField('CANCELED'))->configureRequired(true)->configureDefaultValue(0),
            (new IntegerField('IS_ONLINE'))->configureRequired(true)->configureDefaultValue(0),
            (new DatetimeField('DATE_CREATE'))->configureNullable(true),
            (new DatetimeField('DATE_UPDATE'))->configureNullable(true),
        ];
    }
}

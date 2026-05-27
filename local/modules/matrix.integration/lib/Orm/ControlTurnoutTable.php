<?php

namespace Matrix\Integration\Orm;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DateField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

/**
 * ORM: matrix_control_turnouts
 */
class ControlTurnoutTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'matrix_control_turnouts';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary(true)
                ->configureAutocomplete(true),
            (new IntegerField('MATRIX_ID'))->configureRequired(true),
            (new IntegerField('MATRIX_PATIENT_ID'))->configureRequired(true),
            (new IntegerField('BITRIX_CONTACT_ID'))->configureNullable(true),
            (new IntegerField('MATRIX_DOCTOR_ID'))->configureNullable(true),
            (new IntegerField('BITRIX_USER_ID'))->configureNullable(true),
            (new StringField('DOCTOR_NAME'))->configureNullable(true),
            (new DateField('TURNOUT_DATE'))
                ->configureColumnName('DATE')
                ->configureRequired(true),
            (new IntegerField('NOTIFIED'))->configureRequired(true)->configureDefaultValue(0),
            (new IntegerField('BITRIX_ACTIVITY_ID'))->configureNullable(true),
            (new DatetimeField('DATE_CREATE'))->configureNullable(true),
        ];
    }
}

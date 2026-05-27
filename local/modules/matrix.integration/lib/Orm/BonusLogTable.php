<?php

namespace Matrix\Integration\Orm;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\FloatField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

/**
 * ORM: matrix_bonus_log
 */
class BonusLogTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'matrix_bonus_log';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary(true)
                ->configureAutocomplete(true),
            (new IntegerField('MATRIX_LOG_ID'))->configureNullable(true),
            (new IntegerField('MATRIX_PATIENT_ID'))->configureRequired(true),
            (new IntegerField('BITRIX_CONTACT_ID'))->configureNullable(true),
            (new StringField('TYPE'))->configureNullable(true)->configureSize(100),
            (new FloatField('AMOUNT'))->configureNullable(true),
            (new FloatField('BALANCE'))->configureNullable(true),
            (new DatetimeField('ACTIVATED_AT'))->configureNullable(true),
            (new DatetimeField('DEACTIVATED_AT'))->configureNullable(true),
            (new StringField('STATUS'))->configureNullable(true)->configureSize(50),
            (new IntegerField('NOTIFIED'))->configureRequired(true)->configureDefaultValue(0),
            (new DatetimeField('DATE_CREATE'))->configureNullable(true),
        ];
    }
}

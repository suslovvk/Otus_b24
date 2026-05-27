<?php

namespace Matrix\Integration\Orm;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\FloatField;
use Bitrix\Main\ORM\Fields\IntegerField;

/**
 * ORM: matrix_bonus
 */
class BonusTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'matrix_bonus';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary(true)
                ->configureAutocomplete(true),
            (new IntegerField('MATRIX_PATIENT_ID'))->configureRequired(true),
            (new IntegerField('BITRIX_CONTACT_ID'))->configureNullable(true),
            (new FloatField('BONUS_BALANCE'))->configureRequired(true)->configureDefaultValue(0.0),
            (new DatetimeField('DATE_UPDATE'))->configureNullable(true),
        ];
    }
}

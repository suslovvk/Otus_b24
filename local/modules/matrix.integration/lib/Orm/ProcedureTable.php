<?php

namespace Matrix\Integration\Orm;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\FloatField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

/**
 * ORM: matrix_procedures
 */
class ProcedureTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'matrix_procedures';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary(true)
                ->configureAutocomplete(true),
            (new IntegerField('MATRIX_ID'))->configureRequired(true),
            (new StringField('NAME'))->configureRequired(true)->configureSize(255),
            (new IntegerField('DURATION'))->configureRequired(true),
            (new FloatField('COST'))->configureNullable(true),
            (new IntegerField('ONLINE'))->configureRequired(true)->configureDefaultValue(0),
            (new TextField('DOCTOR_IDS'))->configureNullable(true),
            (new TextField('CABINET_IDS'))->configureNullable(true),
            (new TextField('SPECIALTY_IDS'))->configureNullable(true),
            (new DatetimeField('DATE_CREATE'))->configureNullable(true),
            (new DatetimeField('DATE_UPDATE'))->configureNullable(true),
        ];
    }
}

<?php

namespace Matrix\Integration\Orm;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\FloatField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\TextField;

/**
 * ORM: matrix_receipts
 */
class ReceiptTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'matrix_receipts';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configurePrimary(true)
                ->configureAutocomplete(true),
            (new IntegerField('MATRIX_RECEIPT_ID'))->configureRequired(true),
            (new IntegerField('MATRIX_PATIENT_ID'))->configureRequired(true),
            (new IntegerField('BITRIX_CONTACT_ID'))->configureNullable(true),
            (new IntegerField('BITRIX_DEAL_ID'))->configureNullable(true),
            (new IntegerField('TYPE'))->configureNullable(true),
            (new FloatField('SUM'))->configureNullable(true),
            (new FloatField('PAID'))->configureNullable(true),
            (new DatetimeField('CHECKED_AT'))->configureNullable(true),
            (new TextField('ITEMS'))->configureNullable(true),
            (new DatetimeField('DATE_CREATE'))->configureNullable(true),
        ];
    }
}

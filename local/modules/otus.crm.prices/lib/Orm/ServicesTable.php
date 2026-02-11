<?php
namespace Otus\Crm\Prices\Orm;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Класс для работы с таблицей цен/услуг
 *
 * Class ServicesTable
 * @package Otus\Crm\Prices\Orm
 */
class ServicesTable extends DataManager
{
    /**
    * Возвращает название таблицы в базе данных
    *
    * @return string
    */
    public static function getTableName()
    {
        return 'b_otus_prices';
    }


    /**
     * Возвращает описание полей таблицы
     *
     * @return array
     */
    public static function getMap()
    {
        return [
            (new IntegerField('ID'))
            ->configureAutocomplete()
            ->configurePrimary()
            ->configureTitle(Loc::getMessage('OTUS_PRICES_TABLE_ID')),

            (new StringField('NAME'))
            ->configureRequired()
            ->configureTitle(Loc::getMessage('OTUS_PRICES_TABLE_NAME')),

            (new IntegerField('PRICE'))
            ->configureRequired()
            ->configureTitle(Loc::getMessage('OTUS_PRICES_TABLE_PRICE')),

            (new BooleanField('ACTIVE'))
            ->configureDefaultValue('Y')
            ->configureTitle(Loc::getMessage('OTUS_PRICES_TABLE_ACTIVE')),

        ];
    }

}
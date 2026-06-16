<?php

namespace Otus\Callback\Requests\Orm;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Хранилище заявок на обратный звонок.
 */
class CallbackRequestTable extends DataManager
{
    public const STATUS_NEW = 'NEW';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_DONE = 'DONE';

    /**
     * @return string
     */
    public static function getTableName(): string
    {
        return 'b_otus_callback_requests';
    }

    /**
     * @return array
     */
    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))
                ->configureAutocomplete()
                ->configurePrimary()
                ->configureTitle(Loc::getMessage('OTUS_CALLBACK_REQUESTS_FIELD_ID')),

            (new StringField('NAME'))
                ->configureRequired()
                ->configureSize(255)
                ->configureTitle(Loc::getMessage('OTUS_CALLBACK_REQUESTS_FIELD_NAME')),

            (new StringField('PHONE'))
                ->configureRequired()
                ->configureSize(50)
                ->configureTitle(Loc::getMessage('OTUS_CALLBACK_REQUESTS_FIELD_PHONE')),

            (new StringField('COMMENT'))
                ->configureNullable()
                ->configureTitle(Loc::getMessage('OTUS_CALLBACK_REQUESTS_FIELD_COMMENT')),

            (new StringField('STATUS'))
                ->configureSize(20)
                ->configureDefaultValue(self::STATUS_NEW)
                ->configureTitle(Loc::getMessage('OTUS_CALLBACK_REQUESTS_FIELD_STATUS')),

            (new DatetimeField('DATE_CREATE'))
                ->configureDefaultValue(static fn () => new DateTime())
                ->configureTitle(Loc::getMessage('OTUS_CALLBACK_REQUESTS_FIELD_DATE_CREATE')),
        ];
    }

    /**
     * @return string[]
     */
    public static function getAllowedStatuses(): array
    {
        return [self::STATUS_NEW, self::STATUS_IN_PROGRESS, self::STATUS_DONE];
    }
}

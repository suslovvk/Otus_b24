<?php

namespace Otus\Orm;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\ORM\Fields\Relations\ManyToMany;

/**
 * Class DepartamentsTable
 * 
 * Fields:
 * 
 * id int mandatory
 * departament_name string(255) mandatory
 * 
 *
 * @package Bitrix\Clinic
 **/

class DepartamentsTable extends DataManager
{
	/**
	 * Returns DB table name for entity.
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return 'otus_clinic_departaments';
	}

	/**
	 * Returns entity map definition.
	 *
	 * @return array
	 */
	public static function getMap()
	{
		return [
			(new IntegerField('id'))
                ->configurePrimary(true)
			    ->configureAutocomplete(true),
			
            (new StringField('departament_name',
				[
					'validation' => function()
					{
						return[
							new LengthValidator(null, 255),
						];
					},
				]
			)) 
                ->configureRequired(true),
		];
	}
}

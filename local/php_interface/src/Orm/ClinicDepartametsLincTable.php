<?php

namespace Otus\Orm;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;

/**
 * Class DepartametsLincTable
 * 
 * Fields:
 * 
 * id int mandatory
 * departament_id int mandatory
 * doctor_id int mandatory
 *
 *
 * @package Bitrix\Clinic
 **/

class DepartametsLincTable extends DataManager
{
	/**
	 * Returns DB table name for entity.
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return 'otus_clinic_departamets_linc';
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
                ->configureTitle(Loc::getMessage('DEPARTAMETS_LINC_ENTITY_ID_FIELD'))
				->configurePrimary(true)
				->configureAutocomplete(true),

			(new IntegerField('departament_id'))                
				->configureRequired(true),
			
            (new IntegerField('doctor_id',))
				->configureRequired(true),
		];
	}
}
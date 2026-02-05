<?php

namespace Otus\Orm;

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

class ClinicDepartametsLincTable extends DataManager
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
			(new IntegerField('ID'))               
				->configurePrimary(true)
				->configureAutocomplete(true),

			(new IntegerField('DEPARTAMENT_ID'))                
				->configureRequired(true),
			
            (new IntegerField('DOCTOR_ID'))
				->configureRequired(true),
		];
	}
}
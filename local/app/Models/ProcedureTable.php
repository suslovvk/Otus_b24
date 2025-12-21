<?php
namespace Models;

class ProcedureTable extends AbstractIblockPropertyValuesTable
{
    const IBLOCK_ID = 18; // ID инфоблока процедур 
    
    public static function getProcedureById($id)
    {
        return self::getList([
            'select' => ['IBLOCK_ELEMENT_ID', 'ELEMENT.NAME', 'ELEMENT.DETAIL_TEXT'],
            'filter' => ['=ELEMENT.ID' => $id]
        ])->fetch();
    }
}
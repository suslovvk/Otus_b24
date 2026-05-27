<?php

use Bitrix\Crm\ContactTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

class MatrixCrmPatientReceiptsComponent extends CBitrixComponent
{
    public function executeComponent(): void
    {
        if (!Loader::includeModule('matrix.integration') || !Loader::includeModule('crm')) {
            return;
        }

        $entityId     = (int) ($this->arParams['ENTITY_ID'] ?? 0);
        $entityTypeId = (int) ($this->arParams['ENTITY_TYPE_ID'] ?? 0);

        if ($entityId <= 0 || $entityTypeId !== 3) {
            return;
        }

        $contact = ContactTable::getRow([
            'filter' => ['=ID' => $entityId],
            'select' => ['ID', 'UF_MATRIX_PATIENT_ID'],
        ]);

        $this->arResult = [
            'CONTACT_ID' => $entityId,
            'PATIENT_ID' => (int) ($contact['UF_MATRIX_PATIENT_ID'] ?? 0),
            'AJAX_URL'   => '/local/modules/matrix.integration/admin/crm_tab_ajax.php',
            'SESSID'     => bitrix_sessid(),
        ];

        $this->includeComponentTemplate();
    }
}

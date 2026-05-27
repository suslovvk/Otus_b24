<?php

use Bitrix\Crm\ContactTable;
use Bitrix\Main\Grid\Options as GridOptions;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\Date as BitrixDate;
use Bitrix\Main\UI\PageNavigation;
use Matrix\Integration\Orm\BonusLogTable;

Loc::loadMessages(__FILE__);

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

class MatrixCrmPatientBalanceComponent extends CBitrixComponent
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

        $this->prepareBonusLogGrid($entityId);

        $this->includeComponentTemplate();
    }

    private function prepareBonusLogGrid(int $contactId): void
    {
        $gridId  = 'MATRIX_BONUS_LOG_' . $contactId;
        $headers = [
            ['id' => 'ACTIVATED_AT',   'name' => Loc::getMessage('MATRIX_BALANCE_GRID_COL_ACTIVATED'), 'sort' => 'ACTIVATED_AT',   'default' => true],
            ['id' => 'AMOUNT',         'name' => Loc::getMessage('MATRIX_BALANCE_GRID_COL_AMOUNT'),    'sort' => 'AMOUNT',         'default' => true],
            ['id' => 'DEACTIVATED_AT', 'name' => Loc::getMessage('MATRIX_BALANCE_GRID_COL_DEACTIVATED'), 'sort' => 'DEACTIVATED_AT', 'default' => true],
            ['id' => 'STATUS',         'name' => Loc::getMessage('MATRIX_BALANCE_GRID_COL_STATUS'),                                 'default' => true],
        ];

        $gridOptions = new GridOptions($gridId);
        $navParams   = $gridOptions->getNavParams(['nPageSize' => 15]);

        $nav = new PageNavigation($gridId);
        $nav->allowAllRecords(false)
            ->setPageSize($navParams['nPageSize'])
            ->initFromUri();

        $sort = $gridOptions->getSorting([
            'sort' => ['ACTIVATED_AT' => 'DESC'],
            'vars' => ['by' => 'by', 'order' => 'order'],
        ]);

        $total = BonusLogTable::getCount(['=BITRIX_CONTACT_ID' => $contactId]);
        $nav->setRecordCount($total);

        $rows = BonusLogTable::getList([
            'filter' => ['=BITRIX_CONTACT_ID' => $contactId],
            'select' => ['AMOUNT', 'ACTIVATED_AT', 'DEACTIVATED_AT', 'STATUS'],
            'order'  => $sort['sort'],
            'limit'  => $nav->getLimit(),
            'offset' => $nav->getOffset(),
        ])->fetchAll();

        $now      = time();
        $gridRows = [];

        foreach ($rows as $row) {
            $deactAtObj   = $row['DEACTIVATED_AT'];
            $actAtObj     = $row['ACTIVATED_AT'];
            $storedStatus = trim((string) ($row['STATUS'] ?? ''));

            if ($storedStatus !== '') {
                $lower    = mb_strtolower($storedStatus, 'UTF-8');
                $isActive = str_starts_with($lower, 'актив')
                    || in_array($lower, ['active', 'y', '1'], true);
            } elseif ($deactAtObj instanceof BitrixDate) {
                $isActive = $deactAtObj->getTimestamp() > $now;
            } else {
                $isActive = true;
            }

            $statusHtml = $isActive
                ? '<span style="color:#2e7d32;font-weight:600">' . Loc::getMessage('MATRIX_BALANCE_STATUS_ACTIVE') . '</span>'
                : '<span style="color:#c62828;font-weight:600">' . Loc::getMessage('MATRIX_BALANCE_STATUS_INACTIVE') . '</span>';

            $actStr   = ($actAtObj instanceof BitrixDate)   ? $actAtObj->format('d.m.Y H:i:s')   : '—';
            $deactStr = ($deactAtObj instanceof BitrixDate) ? $deactAtObj->format('d.m.Y H:i:s') : '—';

            $gridRows[] = [
                'data'    => [
                    'ACTIVATED_AT'   => $actStr,
                    'AMOUNT'         => number_format((float) ($row['AMOUNT'] ?? 0), 0, '.', ' ') . ' б.',
                    'DEACTIVATED_AT' => $deactStr,
                    'STATUS'         => $isActive ? Loc::getMessage('MATRIX_BALANCE_STATUS_ACTIVE_TEXT') : Loc::getMessage('MATRIX_BALANCE_STATUS_INACTIVE_TEXT'),
                ],
                'columns' => ['STATUS' => $statusHtml],
                'actions' => [],
            ];
        }

        $this->arResult['BONUS_LOG_GRID_ID'] = $gridId;
        $this->arResult['BONUS_LOG_HEADERS'] = $headers;
        $this->arResult['BONUS_LOG_ROWS']    = $gridRows;
        $this->arResult['BONUS_LOG_NAV']     = $nav;
    }
}

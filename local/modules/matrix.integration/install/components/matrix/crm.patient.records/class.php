<?php

use Bitrix\Crm\ContactTable;
use Bitrix\Main\Grid\Options as GridOptions;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\Date as BitrixDate;
use Bitrix\Main\UI\PageNavigation;
use Matrix\Integration\Orm\RecordTable;

Loc::loadMessages(__FILE__);

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

class MatrixCrmPatientRecordsComponent extends CBitrixComponent
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

        $this->prepareRecordsGrid($entityId);

        $this->includeComponentTemplate();
    }

    private function prepareRecordsGrid(int $contactId): void
    {
        $gridId  = 'MATRIX_RECORDS_' . $contactId;
        $headers = [
            ['id' => 'DATETIME',       'name' => Loc::getMessage('MATRIX_RECORDS_GRID_COL_DATETIME'),  'sort' => 'DATETIME',       'default' => true],
            ['id' => 'DOCTOR_NAME',    'name' => Loc::getMessage('MATRIX_RECORDS_GRID_COL_DOCTOR'),    'sort' => 'DOCTOR_NAME',    'default' => true],
            ['id' => 'PROCEDURE_NAME', 'name' => Loc::getMessage('MATRIX_RECORDS_GRID_COL_PROCEDURE'), 'sort' => 'PROCEDURE_NAME', 'default' => true],
            ['id' => 'STATUS',         'name' => Loc::getMessage('MATRIX_RECORDS_GRID_COL_STATUS'),                                'default' => true],
            ['id' => 'TYPE',           'name' => Loc::getMessage('MATRIX_RECORDS_GRID_COL_TYPE'),                                  'default' => true],
            ['id' => 'DETAIL',         'name' => '',                                                                               'default' => true],
        ];

        $gridOptions = new GridOptions($gridId);
        $navParams   = $gridOptions->getNavParams(['nPageSize' => 15]);

        $nav = new PageNavigation($gridId);
        $nav->allowAllRecords(false)
            ->setPageSize($navParams['nPageSize'])
            ->initFromUri();

        $sort = $gridOptions->getSorting([
            'sort' => ['DATETIME' => 'DESC'],
            'vars' => ['by' => 'by', 'order' => 'order'],
        ]);

        $total = RecordTable::getCount(['=BITRIX_CONTACT_ID' => $contactId]);
        $nav->setRecordCount($total);

        $rows = RecordTable::getList([
            'filter' => ['=BITRIX_CONTACT_ID' => $contactId],
            'select' => ['MATRIX_RECORD_ID', 'DATETIME', 'STATUS', 'CANCELED', 'IS_ONLINE',
                         'DOCTOR_NAME', 'PROCEDURE_NAME'],
            'order'  => $sort['sort'],
            'limit'  => $nav->getLimit(),
            'offset' => $nav->getOffset(),
        ])->fetchAll();

        $now      = time();
        $gridRows = [];

        $statusMeta = [
            'completed' => [Loc::getMessage('MATRIX_RECORDS_STATUS_COMPLETED_BADGE'), '#2e7d32'],
            'canceled'  => [Loc::getMessage('MATRIX_RECORDS_STATUS_CANCELED_BADGE'),  '#c62828'],
            'scheduled' => [Loc::getMessage('MATRIX_RECORDS_STATUS_SCHEDULED_BADGE'), '#e65100'],
        ];

        foreach ($rows as $row) {
            $dtObj    = $row['DATETIME'];
            $canceled = (int) ($row['CANCELED'] ?? 0);
            $stored   = strtolower(trim((string) ($row['STATUS'] ?? '')));

            // Derive display status from stored value, canceled flag, or datetime
            if ($stored !== '') {
                $statusKey = $stored;
            } elseif ($canceled) {
                $statusKey = 'canceled';
            } elseif ($dtObj instanceof BitrixDate) {
                $statusKey = $dtObj->getTimestamp() < $now ? 'completed' : 'scheduled';
            } else {
                $statusKey = '';
            }

            [$statusLabel, $statusColor] = $statusMeta[$statusKey] ?? ['—', '#666'];
            $statusHtml = "<span style='color:{$statusColor};font-weight:600'>{$statusLabel}</span>";

            $isOnline  = (int) ($row['IS_ONLINE'] ?? 0);
            $typeLabel = $isOnline ? Loc::getMessage('MATRIX_RECORDS_TYPE_ONLINE') : Loc::getMessage('MATRIX_RECORDS_TYPE_CLINIC');
            $typeHtml  = $isOnline
                ? '<span style="color:#1565c0;font-weight:600">' . Loc::getMessage('MATRIX_RECORDS_TYPE_ONLINE') . '</span>'
                : '<span style="color:#555">' . Loc::getMessage('MATRIX_RECORDS_TYPE_CLINIC') . '</span>';

            $dtStr = ($dtObj instanceof BitrixDate)
                ? (new \DateTimeImmutable('@' . $dtObj->getTimestamp()))
                    ->setTimezone(new \DateTimeZone('+05:00'))
                    ->format('d.m.Y H:i:s')
                : '—';

            $detailJson = htmlspecialchars(json_encode([
                'id'        => (int) $row['MATRIX_RECORD_ID'],
                'dt'        => $dtStr,
                'doctor'    => (string) ($row['DOCTOR_NAME'] ?? ''),
                'procedure' => (string) ($row['PROCEDURE_NAME'] ?? ''),
                'status'    => $statusLabel,
                'statusKey' => $statusKey,
                'type'      => $typeLabel,
            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

            $detailBtn = "<button class='ui-btn ui-btn-xs ui-btn-light mx-record-detail'"
                . " data-r='{$detailJson}'>" . Loc::getMessage('MATRIX_RECORDS_BTN_DETAILS') . "</button>";

            $gridRows[] = [
                'data'    => [
                    'DATETIME'       => $dtStr,
                    'DOCTOR_NAME'    => (string) ($row['DOCTOR_NAME'] ?? ''),
                    'PROCEDURE_NAME' => (string) ($row['PROCEDURE_NAME'] ?? ''),
                    'STATUS'         => $statusLabel,
                    'TYPE'           => $typeLabel,
                    'DETAIL'         => '',
                ],
                'columns' => [
                    'STATUS' => $statusHtml,
                    'TYPE'   => $typeHtml,
                    'DETAIL' => $detailBtn,
                ],
                'actions' => [],
            ];
        }

        $this->arResult['RECORDS_GRID_ID'] = $gridId;
        $this->arResult['RECORDS_HEADERS'] = $headers;
        $this->arResult['RECORDS_ROWS']    = $gridRows;
        $this->arResult['RECORDS_NAV']     = $nav;
    }
}

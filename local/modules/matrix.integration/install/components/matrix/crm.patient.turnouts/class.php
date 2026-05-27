<?php

use Bitrix\Crm\ContactTable;
use Bitrix\Main\Grid\Options as GridOptions;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\Date as BitrixDate;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\UI\PageNavigation;
use Bitrix\Main\UserTable;
use Matrix\Integration\Orm\ControlTurnoutTable;

Loc::loadMessages(__FILE__);

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

class MatrixCrmPatientTurnoutsComponent extends CBitrixComponent
{
    public function executeComponent(): void
    {
        if (!Loader::includeModule('matrix.integration') || !Loader::includeModule('crm')) {
            return;
        }

        $entityId     = (int) ($this->arParams['ENTITY_ID'] ?? 0);
        $entityTypeId = (int) ($this->arParams['ENTITY_TYPE_ID'] ?? 0);
        $isTabMode    = ($entityId > 0 && $entityTypeId === 3);

        $this->arResult = [
            'IS_TAB_MODE' => $isTabMode,
            'CONTACT_ID'  => 0,
            'PATIENT_ID'  => 0,
            'AJAX_URL'    => '/local/modules/matrix.integration/admin/crm_tab_ajax.php',
            'SESSID'      => bitrix_sessid(),
        ];

        if ($isTabMode) {
            $contact = ContactTable::getRow([
                'filter' => ['=ID' => $entityId],
                'select' => ['ID', 'UF_MATRIX_PATIENT_ID'],
            ]);
            $this->arResult['CONTACT_ID'] = $entityId;
            $this->arResult['PATIENT_ID'] = (int) ($contact['UF_MATRIX_PATIENT_ID'] ?? 0);
        }

        $this->prepareGrid($isTabMode ? $entityId : 0);

        if (!$isTabMode) {
            $this->setupPublicLayout();
        }

        $this->includeComponentTemplate();
    }

    private function setupPublicLayout(): void
    {
        $bodyClass = (string) ($GLOBALS['APPLICATION']->GetPageProperty('BodyClass') ?? '');
        $GLOBALS['APPLICATION']->SetPageProperty(
            'BodyClass',
            trim($bodyClass . ' no-paddings pagetitle-toolbar-field-view flexible-layout crm-toolbar')
        );

        \Bitrix\UI\Toolbar\Facade\Toolbar::setTitleNoShrink();
        \Bitrix\UI\Toolbar\Facade\Toolbar::addFilter([
            'GRID_ID'                   => $this->arResult['GRID_ID'],
            'FILTER_ID'                 => $this->arResult['FILTER_ID'],
            'FILTER'                    => $this->arResult['FILTER_FIELDS'],
            'ENABLE_LABEL'              => true,
            'ENABLE_ADDITIONAL_FILTERS' => true,
        ]);
    }

    private function prepareGrid(int $contactId): void
    {
        $isTabMode = ($contactId > 0);
        $gridId    = $isTabMode ? 'MATRIX_TURNOUTS_' . $contactId : 'MATRIX_TURNOUTS_ALL';
        $filterId  = $isTabMode ? null : 'MATRIX_TURNOUTS_FILTER';

        $headers = [];
        if (!$isTabMode) {
            $headers[] = ['id' => 'PATIENT_NAME', 'name' => Loc::getMessage('MATRIX_TURNOUTS_GRID_COL_PATIENT'), 'default' => true];
        }
        $headers[] = ['id' => 'TURNOUT_DATE', 'name' => Loc::getMessage('MATRIX_TURNOUTS_GRID_COL_DATE'),     'sort' => 'TURNOUT_DATE', 'default' => true];
        $headers[] = ['id' => 'DOCTOR_NAME',  'name' => Loc::getMessage('MATRIX_TURNOUTS_GRID_COL_DOCTOR'),                             'default' => true];
        $headers[] = ['id' => 'NOTIFIED',     'name' => Loc::getMessage('MATRIX_TURNOUTS_GRID_COL_STATUS'),                             'default' => true];
        $headers[] = ['id' => 'ACTIVITY',     'name' => Loc::getMessage('MATRIX_TURNOUTS_GRID_COL_ACTIVITY'),                           'default' => true];

        $gridOptions = new GridOptions($gridId);
        $navParams   = $gridOptions->getNavParams(['nPageSize' => 15]);

        $nav = new PageNavigation($gridId);
        $nav->allowAllRecords(false)
            ->setPageSize($navParams['nPageSize'])
            ->initFromUri();

        $sort = $gridOptions->getSorting([
            'sort' => ['TURNOUT_DATE' => 'DESC'],
            'vars' => ['by' => 'by', 'order' => 'order'],
        ]);

        $sortField = array_key_first($sort['sort']);
        $sortDir   = reset($sort['sort']);
        if ($sortField !== 'TURNOUT_DATE') {
            $sortField = 'TURNOUT_DATE';
            $sortDir   = 'DESC';
        }

        $dbFilter = [];
        if ($isTabMode) {
            $dbFilter['=BITRIX_CONTACT_ID'] = $contactId;
        } elseif ($filterId !== null) {
            $filterOptions = new FilterOptions($filterId);
            $filterFields  = $filterOptions->getFilter($this->getFilterFields());

            if (!empty($filterFields['PATIENT_SEARCH'])) {
                $search   = trim((string) $filterFields['PATIENT_SEARCH']);
                $foundIds = [];
                foreach (ContactTable::getList(['filter' => ['%NAME' => $search],      'select' => ['ID']])->fetchAll() as $c) {
                    $foundIds[] = (int) $c['ID'];
                }
                foreach (ContactTable::getList(['filter' => ['%LAST_NAME' => $search], 'select' => ['ID']])->fetchAll() as $c) {
                    $foundIds[] = (int) $c['ID'];
                }
                $dbFilter['@BITRIX_CONTACT_ID'] = array_unique($foundIds) ?: [0];
            }

            if (!empty($filterFields['DOCTOR_ID'])) {
                $dbFilter['=BITRIX_USER_ID'] = (int) $filterFields['DOCTOR_ID'];
            }

            if (isset($filterFields['NOTIFIED']) && $filterFields['NOTIFIED'] !== '') {
                $dbFilter['=NOTIFIED'] = (int) $filterFields['NOTIFIED'];
            }

            if (!empty($filterFields['TURNOUT_DATE_from'])) {
                try {
                    $dt = \DateTime::createFromFormat('d.m.Y', $filterFields['TURNOUT_DATE_from'])
                        ?: new \DateTime($filterFields['TURNOUT_DATE_from']);
                    $dbFilter['>=TURNOUT_DATE'] = BitrixDate::createFromPhp($dt);
                } catch (\Throwable) {}
            }
            if (!empty($filterFields['TURNOUT_DATE_to'])) {
                try {
                    $dt = \DateTime::createFromFormat('d.m.Y', $filterFields['TURNOUT_DATE_to'])
                        ?: new \DateTime($filterFields['TURNOUT_DATE_to']);
                    $dbFilter['<=TURNOUT_DATE'] = BitrixDate::createFromPhp($dt);
                } catch (\Throwable) {}
            }
        }

        $total = ControlTurnoutTable::getCount($dbFilter ?: []);
        $nav->setRecordCount($total);

        $rows = ControlTurnoutTable::getList([
            'filter' => $dbFilter ?: [],
            'select' => ['ID', 'BITRIX_CONTACT_ID', 'BITRIX_USER_ID', 'DOCTOR_NAME',
                         'TURNOUT_DATE', 'NOTIFIED', 'BITRIX_ACTIVITY_ID'],
            'order'  => [$sortField => $sortDir],
            'limit'  => $nav->getLimit(),
            'offset' => $nav->getOffset(),
        ])->fetchAll();

        // Batch-load user names only for rows where DOCTOR_NAME is not stored
        $userIds = array_filter(array_unique(array_map(
            fn($r) => (string) ($r['DOCTOR_NAME'] ?? '') === '' ? (int) ($r['BITRIX_USER_ID'] ?? 0) : 0,
            $rows
        )));
        $userMap = [];
        if ($userIds) {
            foreach (UserTable::getList([
                'filter' => ['@ID' => array_values($userIds)],
                'select' => ['ID', 'NAME', 'LAST_NAME'],
            ])->fetchAll() as $u) {
                $userMap[(int) $u['ID']] = trim($u['LAST_NAME'] . ' ' . $u['NAME']);
            }
        }

        // Batch-load patient names for global mode
        $contactMap = [];
        if (!$isTabMode) {
            $cIds = array_filter(array_unique(array_column($rows, 'BITRIX_CONTACT_ID')));
            if ($cIds) {
                foreach (ContactTable::getList([
                    'filter' => ['@ID' => array_values($cIds)],
                    'select' => ['ID', 'NAME', 'LAST_NAME'],
                ])->fetchAll() as $c) {
                    $contactMap[(int) $c['ID']] = trim($c['LAST_NAME'] . ' ' . $c['NAME']);
                }
            }
        }

        $gridRows = [];
        foreach ($rows as $row) {
            $dateObj = $row['TURNOUT_DATE'];
            $dateStr = ($dateObj instanceof BitrixDate) ? $dateObj->format('d.m.Y') : '—';

            $doctorName   = (string) ($row['DOCTOR_NAME'] ?? '') !== ''
                ? (string) $row['DOCTOR_NAME']
                : ($userMap[(int) ($row['BITRIX_USER_ID'] ?? 0)] ?? '—');
            $notified     = (int) ($row['NOTIFIED'] ?? 0);
            $activityId   = (int) ($row['BITRIX_ACTIVITY_ID'] ?? 0);

            $notifiedHtml = $notified
                ? "<span style='color:#2e7d32;font-weight:600'>" . Loc::getMessage('MATRIX_TURNOUTS_STATUS_PROCESSED') . "</span>"
                : "<span style='color:#e65100'>" . Loc::getMessage('MATRIX_TURNOUTS_STATUS_PENDING') . "</span>";

            $activityHtml = $activityId > 0
                ? "<a href='/crm/activity/{$activityId}/' target='_blank'>#" . $activityId . '</a>'
                : "<button class='ui-btn ui-btn-xs ui-btn-light mx-create-activity'"
                  . " data-turnout-id='" . (int) $row['ID'] . "'>" . Loc::getMessage('MATRIX_TURNOUTS_BTN_CREATE_ACTIVITY') . "</button>";

            $data = [
                'TURNOUT_DATE' => $dateStr,
                'DOCTOR_NAME'  => $doctorName,
                'NOTIFIED'     => $notified ? Loc::getMessage('MATRIX_TURNOUTS_STATUS_PROCESSED_TEXT') : Loc::getMessage('MATRIX_TURNOUTS_STATUS_PENDING_TEXT'),
                'ACTIVITY'     => '',
            ];
            $cols = [
                'NOTIFIED' => $notifiedHtml,
                'ACTIVITY' => $activityHtml,
            ];

            if (!$isTabMode) {
                $cId             = (int) ($row['BITRIX_CONTACT_ID'] ?? 0);
                $patientName     = $contactMap[$cId] ?? (Loc::getMessage('MATRIX_TURNOUTS_CONTACT_FALLBACK') . $cId);
                $data['PATIENT_NAME'] = $patientName;
                $cols['PATIENT_NAME'] = $cId > 0
                    ? "<a href='/crm/contact/show/{$cId}/' target='_blank'>"
                      . htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8') . '</a>'
                    : '—';
            }

            $gridRows[] = ['data' => $data, 'columns' => $cols, 'actions' => []];
        }

        $this->arResult['GRID_ID']       = $gridId;
        $this->arResult['HEADERS']       = $headers;
        $this->arResult['ROWS']          = $gridRows;
        $this->arResult['NAV']           = $nav;
        $this->arResult['FILTER_ID']     = $filterId;
        $this->arResult['FILTER_FIELDS'] = $filterId ? $this->getFilterFields() : [];
    }

    private function getFilterFields(): array
    {
        $fields = [
            [
                'id'      => 'PATIENT_SEARCH',
                'name'    => Loc::getMessage('MATRIX_TURNOUTS_FILTER_PATIENT'),
                'type'    => 'string',
                'default' => true,
            ],
            [
                'id'      => 'TURNOUT_DATE',
                'name'    => Loc::getMessage('MATRIX_TURNOUTS_FILTER_DATE'),
                'type'    => 'date',
                'default' => true,
            ],
        ];

        // Фильтры по врачу и статусу — только в глобальном режиме
        $fields[] = [
            'id'      => 'DOCTOR_ID',
            'name'    => Loc::getMessage('MATRIX_TURNOUTS_FILTER_DOCTOR'),
            'type'    => 'list',
            'items'   => $this->getDoctorItems(),
            'default' => true,
        ];
        $fields[] = [
            'id'      => 'NOTIFIED',
            'name'    => Loc::getMessage('MATRIX_TURNOUTS_FILTER_STATUS'),
            'type'    => 'list',
            'items'   => [
                ''  => Loc::getMessage('MATRIX_TURNOUTS_FILTER_STATUS_ALL'),
                '1' => Loc::getMessage('MATRIX_TURNOUTS_STATUS_PROCESSED'),
                '0' => Loc::getMessage('MATRIX_TURNOUTS_STATUS_PENDING'),
            ],
            'default' => true,
        ];

        return $fields;
    }

    private function getDoctorItems(): array
    {
        $items = ['' => Loc::getMessage('MATRIX_TURNOUTS_FILTER_STATUS_ALL')];
        $rs = UserTable::getList([
            'filter' => ['>UF_MATRIX_DOCTOR_ID' => 0, '=ACTIVE' => 'Y'],
            'select' => ['ID', 'NAME', 'LAST_NAME'],
            'order'  => ['LAST_NAME' => 'ASC', 'NAME' => 'ASC'],
        ]);
        while ($row = $rs->fetch()) {
            $items[(int) $row['ID']] = trim($row['LAST_NAME'] . ' ' . $row['NAME']);
        }
        return $items;
    }
}

<?php

use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Matrix\Integration\Agent\AgentManager;
use Matrix\Integration\CrmList\ContactListButton;
use Matrix\Integration\CrmTab\ContactTab;
use Matrix\Integration\EntitySelector\DoctorEntityProvider;

Loc::loadMessages(__FILE__);

class matrix_integration extends CModule
{
    public $MODULE_ID          = 'matrix.integration';
    public $MODULE_VERSION     = '';
    public $MODULE_VERSION_DATE = '';
    public $MODULE_NAME        = '';
    public $MODULE_DESCRIPTION = '';
    public $MODULE_SORT        = 100;
    public $PARTNER_NAME       = '';
    public $PARTNER_URI        = '';
    
    public function __construct()
    {
        $versionFile = __DIR__ . '/version.php';

        if (file_exists($versionFile)) {
            $arModuleVersion = [];
            include $versionFile;
            $this->MODULE_VERSION = $arModuleVersion['VERSION'] ?? '1.0.0';
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'] ?? date('Y-m-d');
        }

        $this->MODULE_NAME = Loc::getMessage('MATRIX_INTEGRATION_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('MATRIX_INTEGRATION_MODULE_DESCRIPTION');
        $this->PARTNER_NAME = Loc::getMessage('MATRIX_INTEGRATION_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('MATRIX_INTEGRATION_PARTNER_URI');
    }

    public function DoInstall(): void
    {
        ModuleManager::registerModule($this->MODULE_ID);

        $this->InstallDB();
        $this->InstallComponents();
        $this->InstallPublicPage();
        $this->RegisterAutoLoad();
        $this->InstallUserFields();
        $this->RegisterAgents();
        $this->RegisterCrmTab();
        $this->RegisterDoctorEntityProvider();
        $this->RegisterCrmListButton();
    }

    public function DoUninstall(): void
    {
        Loader::includeModule($this->MODULE_ID);

        $this->UnRegisterCrmListButton();
        $this->UnRegisterAgents();
        $this->UnRegisterCrmTab();
        $this->UnRegisterDoctorEntityProvider();
        $this->UnInstallUserFields();
        $this->UnInstallDB();
        $this->UnInstallPublicPage();
        $this->UnInstallComponents();

        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    public function InstallComponents(): void
    {
        $src = __DIR__ . '/components';
        $dst = $_SERVER['DOCUMENT_ROOT'] . '/local/components';

        CopyDirFiles($src, $dst, true, true);
    }

    public function InstallPublicPage(): void
    {
        // Убираем старые версии, если остались
        $oldAdmin = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/matrix_control_turnouts.php';
        if (file_exists($oldAdmin)) {
            unlink($oldAdmin);
        }
        $oldFlat = $_SERVER['DOCUMENT_ROOT'] . '/local/matrix_turnouts.php';
        if (file_exists($oldFlat)) {
            unlink($oldFlat);
        }

        $src = __DIR__ . '/pages/crm/matrix-turnouts/index.php';
        $dir = $_SERVER['DOCUMENT_ROOT'] . '/crm/matrix-turnouts/';
        $dst = $dir . 'index.php';

        if (file_exists($src)) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            copy($src, $dst);
        }
    }

    public function UnInstallPublicPage(): void
    {
        $file = $_SERVER['DOCUMENT_ROOT'] . '/crm/matrix-turnouts/index.php';
        if (file_exists($file)) {
            unlink($file);
        }
        $dir = $_SERVER['DOCUMENT_ROOT'] . '/crm/matrix-turnouts/';
        if (is_dir($dir) && count(scandir($dir)) === 2) {
            rmdir($dir);
        }
    }

    public function UnInstallComponents(): void
    {
        $components = [
            'matrix/crm.patient.balance',
            'matrix/crm.patient.receipts',
            'matrix/crm.patient.records',
            'matrix/crm.patient.turnouts',
        ];

        foreach ($components as $component) {
            $path = $_SERVER['DOCUMENT_ROOT'] . '/local/components/' . $component;
            if (is_dir($path)) {
                DeleteDirFilesEx($path);
            }
        }
    }

    public function InstallDB(): void
    {
        $connection = Application::getConnection();
        $sqlFile    = __DIR__ . '/db/install.sql';

        if (!file_exists($sqlFile)) {
            return;
        }

        $sql = file_get_contents($sqlFile);
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            static fn(string $s) => $s !== ''
        );

        foreach ($statements as $statement) {
            $connection->query($statement);
        }
    }

    public function UnInstallDB(): void
    {
        $connection = Application::getConnection();

        $tables = [
            'matrix_procedures',
            'matrix_records',
            'matrix_balance',
            'matrix_bonus',
            'matrix_bonus_log',
            'matrix_control_turnouts',
            'matrix_receipts',
        ];

        foreach ($tables as $table) {
            $connection->query('DROP TABLE IF EXISTS `' . $table . '`');
        }
    }

    public function RegisterAutoLoad(): void
    {
        Loader::registerAutoLoadClasses($this->MODULE_ID, [
            'Matrix\Integration\Util\ApiItem' => 'lib/Util/ApiItem.php',
            'Matrix\Integration\Api\MatrixClient' => 'lib/Api/MatrixClient.php',
            'Matrix\Integration\Orm\ProcedureTable' => 'lib/Orm/ProcedureTable.php',
            'Matrix\Integration\Orm\RecordTable' => 'lib/Orm/RecordTable.php',
            'Matrix\Integration\Orm\BalanceTable' => 'lib/Orm/BalanceTable.php',
            'Matrix\Integration\Orm\BonusTable' => 'lib/Orm/BonusTable.php',
            'Matrix\Integration\Orm\BonusLogTable' => 'lib/Orm/BonusLogTable.php',
            'Matrix\Integration\Orm\ControlTurnoutTable' => 'lib/Orm/ControlTurnoutTable.php',
            'Matrix\Integration\Orm\DoctorProcedureTable' => 'lib/Orm/DoctorProcedureTable.php',
            'Matrix\Integration\Orm\ReceiptTable' => 'lib/Orm/ReceiptTable.php',
            'Matrix\Integration\Sync\DoctorSync' => 'lib/Sync/DoctorSync.php',
            'Matrix\Integration\Sync\ProductSync' => 'lib/Sync/ProductSync.php',
            'Matrix\Integration\Sync\PatientSync' => 'lib/Sync/PatientSync.php',
            'Matrix\Integration\Sync\RecordSync' => 'lib/Sync/RecordSync.php',
            'Matrix\Integration\Sync\BalanceSync' => 'lib/Sync/BalanceSync.php',
            'Matrix\Integration\Sync\BonusSync' => 'lib/Sync/BonusSync.php',
            'Matrix\Integration\Sync\ControlTurnoutSync' => 'lib/Sync/ControlTurnoutSync.php',
            'Matrix\Integration\Sync\ReceiptSync' => 'lib/Sync/ReceiptSync.php',
            'Matrix\Integration\Booking\SlotManager' => 'lib/Booking/SlotManager.php',
            'Matrix\Integration\Agent\AgentManager' => 'lib/Agent/AgentManager.php',
            'Matrix\Integration\CrmTab\ContactTab' => 'lib/CrmTab/ContactTab.php',
            'Matrix\Integration\EntitySelector\DoctorEntityProvider' => 'lib/EntitySelector/DoctorEntityProvider.php',
            'Matrix\Integration\CrmList\ContactListButton'           => 'lib/CrmList/ContactListButton.php',
        ]);
    }


    public function InstallUserFields(): void
    {
        if (!Loader::includeModule('crm')) {
            return;
        }

        $userTypeEntity = new CUserTypeEntity();

        $contactFields = [
            [
                'ENTITY_ID'         => 'CRM_CONTACT',
                'FIELD_NAME'        => 'UF_MATRIX_PATIENT_ID',
                'USER_TYPE_ID'      => 'integer',
                'MULTIPLE'          => 'N',
                'MANDATORY'         => 'N',
                'SHOW_FILTER'       => 'Y',
                'SHOW_IN_LIST'      => 'Y',
                'EDIT_IN_LIST'      => 'N',
                'IS_SEARCHABLE'     => 'N',
                'EDIT_FORM_LABEL'   => [
                    'ru' => Loc::getMessage('MATRIX_UF_PATIENT_ID_LABEL'),
                    'en' => 'Matrix Patient ID',
                ],
            ],
            [
                'ENTITY_ID'         => 'CRM_CONTACT',
                'FIELD_NAME'        => 'UF_MATRIX_SYNCED_AT',
                'USER_TYPE_ID'      => 'datetime',
                'MULTIPLE'          => 'N',
                'MANDATORY'         => 'N',
                'SHOW_FILTER'       => 'N',
                'SHOW_IN_LIST'      => 'N',
                'EDIT_IN_LIST'      => 'N',
                'IS_SEARCHABLE'     => 'N',
                'EDIT_FORM_LABEL'   => [
                    'ru' => Loc::getMessage('MATRIX_UF_SYNCED_AT_LABEL'),
                    'en' => 'Matrix Last Sync',
                ],
            ],
        ];

        foreach ($contactFields as $field) {
            $existing = $userTypeEntity->GetList(
                [],
                ['ENTITY_ID' => $field['ENTITY_ID'], 'FIELD_NAME' => $field['FIELD_NAME']]
            )->Fetch();

            if (!$existing) {
                $userTypeEntity->Add($field);
            }
        }

        // UF_MATRIX_DOCTOR_ID на сотруднике
        $doctorField = [
            'ENTITY_ID'         => 'USER',
            'FIELD_NAME'        => 'UF_MATRIX_DOCTOR_ID',
            'USER_TYPE_ID'      => 'integer',
            'MULTIPLE'          => 'N',
            'MANDATORY'         => 'N',
            'SHOW_FILTER'       => 'Y',
            'SHOW_IN_LIST'      => 'Y',
            'EDIT_IN_LIST'      => 'N',
            'IS_SEARCHABLE'     => 'N',
            'EDIT_FORM_LABEL'   => [
                'ru' => Loc::getMessage('MATRIX_UF_DOCTOR_ID_LABEL'),
                'en' => 'Matrix Doctor ID',
            ],
        ];

        $existing = $userTypeEntity->GetList(
            [],
            ['ENTITY_ID' => 'USER', 'FIELD_NAME' => 'UF_MATRIX_DOCTOR_ID']
        )->Fetch();

        if (!$existing) {
            $userTypeEntity->Add($doctorField);
        }
    }

    public function UnInstallUserFields(): void
    {
        $userTypeEntity = new CUserTypeEntity();

        $fieldsToDelete = [
            ['ENTITY_ID' => 'CRM_CONTACT', 'FIELD_NAME' => 'UF_MATRIX_PATIENT_ID'],
            ['ENTITY_ID' => 'CRM_CONTACT', 'FIELD_NAME' => 'UF_MATRIX_SYNCED_AT'],
            ['ENTITY_ID' => 'USER',        'FIELD_NAME' => 'UF_MATRIX_DOCTOR_ID'],
        ];

        foreach ($fieldsToDelete as $filter) {
            $field = $userTypeEntity->GetList([], $filter)->Fetch();
            if ($field) {
                $userTypeEntity->Delete($field['ID']);
            }
        }
    }

    public function RegisterCrmListButton(): void
    {
        EventManager::getInstance()->registerEventHandler(
            'main',
            'OnEpilog',
            $this->MODULE_ID,
            ContactListButton::class,
            'onEpilog'
        );
    }

    public function UnRegisterCrmListButton(): void
    {
        EventManager::getInstance()->unRegisterEventHandler(
            'main',
            'OnEpilog',
            $this->MODULE_ID,
            ContactListButton::class,
            'onEpilog'
        );
    }

    public function RegisterAgents(): void
    {
        Loader::includeModule($this->MODULE_ID);
        AgentManager::registerAll();
    }

    public function UnRegisterAgents(): void
    {
        AgentManager::unregisterAll();
    }

    public function RegisterCrmTab(): void
    {
        EventManager::getInstance()->registerEventHandler(
            'crm',
            'onEntityDetailsTabsInitialized',
            $this->MODULE_ID,
            ContactTab::class,
            'onTabsInitialized'
        );
    }

    public function UnRegisterCrmTab(): void
    {
        EventManager::getInstance()->unRegisterEventHandler(
            'crm',
            'onEntityDetailsTabsInitialized',
            $this->MODULE_ID,
            ContactTab::class,
            'onTabsInitialized'
        );
    }

    public function RegisterDoctorEntityProvider(): void
    {
        EventManager::getInstance()->registerEventHandler(
            'ui',
            'onEntitySelectorDialogInitialize',
            $this->MODULE_ID,
            DoctorEntityProvider::class,
            'onDialogInitialize'
        );
    }

    public function UnRegisterDoctorEntityProvider(): void
    {
        EventManager::getInstance()->unRegisterEventHandler(
            'ui',
            'onEntitySelectorDialogInitialize',
            $this->MODULE_ID,
            DoctorEntityProvider::class,
            'onDialogInitialize'
        );
    }

}
<?php

use Bitrix\Main\Application;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

/**
 * Класс установщика модуля matrix.integration.
 * Обеспечивает установку и удаление модуля интеграции Битрикс24 с Matrix CRM.
 */
class matrix_integration extends CModule
{
    /** @var string Идентификатор модуля */
    public  $MODULE_ID = 'matrix.integration';

    /** @var string Версия модуля */
    public $MODULE_VERSION;

    /** @var string Дата версии модуля */
    public $MODULE_VERSION_DATE;

    /** @var string Название модуля */
    public $MODULE_NAME;

    /** @var string Описание модуля */
    public $MODULE_DESCRIPTION;

    /** @var string Описание модуля */
    public $MODULE_SORT = 100;

    /** @var string Пратнер */
    public $PARTNER_NAME;
    
    /** @var string Ссылка на партнера */    
    public $PARTNER_URI;
    
    /**
     * Конструктор. Загружает метаданные из version.php.
     */
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

    /**
     * Устанавливает модуль:
     * — регистрирует модуль в системе,
     * — создаёт таблицы БД,
     * — регистрирует автозагрузку классов,
     * — добавляет пользовательские поля к контактам и сотрудникам.
     *
     * @return void
     * @throws SqlQueryException
     */
    public function DoInstall(): void
    {
        ModuleManager::registerModule($this->MODULE_ID);

        $this->InstallDB();
        $this->RegisterAutoLoad();
        $this->InstallUserFields();
    }

    /**
     * Удаляет модуль:
     * — удаляет пользовательские поля,
     * — удаляет таблицы БД,
     * — снимает регистрацию автозагрузки,
     * — снимает регистрацию модуля.
     *
     * @return void
     */
    public function DoUninstall(): void
    {
        $this->UnInstallUserFields();
        $this->UnInstallDB();

        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    /**
     * Создаёт таблицы БД из SQL-файла.
     *
     * @return void
     * @throws SqlQueryException
     */
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

    /**
     * Удаляет таблицы БД модуля.
     *
     * @return void
     * @throws SqlQueryException
     */
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

    /**
     * Регистрирует автозагрузку классов модуля через Bitrix Loader.
     *
     * @return void
     */
    public function RegisterAutoLoad(): void
    {
        Loader::registerAutoLoadClasses($this->MODULE_ID, [
            'Matrix\Integration\Api\MatrixClient' => 'lib/Api/MatrixClient.php',
            'Matrix\Integration\Orm\ProcedureTable' => 'lib/Orm/ProcedureTable.php',
            'Matrix\Integration\Orm\RecordTable' => 'lib/Orm/RecordTable.php',
            'Matrix\Integration\Orm\BalanceTable' => 'lib/Orm/BalanceTable.php',
            'Matrix\Integration\Orm\BonusTable' => 'lib/Orm/BonusTable.php',
            'Matrix\Integration\Orm\BonusLogTable' => 'lib/Orm/BonusLogTable.php',
            'Matrix\Integration\Orm\ControlTurnoutTable' => 'lib/Orm/ControlTurnoutTable.php',
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
            'Matrix\Integration\Report\TurnoutReport' => 'lib/Report/TurnoutReport.php',
        ]);
    }


    /**
     * Создаёт пользовательские поля:
     * — UF_MATRIX_PATIENT_ID (integer) на контакте CRM,
     * — UF_MATRIX_SYNCED_AT (datetime) на контакте CRM,
     * — UF_MATRIX_DOCTOR_ID (integer) на сотруднике.
     *
     * @return void
     */
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
            // Проверяем что поле ещё не существует
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

    /**
     * Удаляет пользовательские поля модуля.
     *
     * @return void
     */
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

    /**
     * Проверка версии D7
     *
     * @return bool
     */
    public function isVersionD7()
    {
        return CheckVersion(ModuleManager::getVersion('main'), '20.00.00');
    }
}
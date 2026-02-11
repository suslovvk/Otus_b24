<?php

use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Entity\Base;
use Otus\Crm\Prices\Orm\ServicesTable;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\SystemException;
use Bitrix\Main\IO\InvalidPathException;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\LoaderException;

Loc::loadMessages(__FILE__);

class otus_crm_prices extends CModule
{
    public $MODULE_ID = 'otus.crm.prices';
    public $MODULE_SORT = 100;
    public $MODULE_VERSION;
    public $MODULE_DESCRIPTION;
    public $MODULE_VERSION_DATE;
    public $PARTNER_NAME;
    public $PARTNER_URI;
    
    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';
        
        
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('OTUS_PRICES_INSTALL_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('OTUS_PRICES_INSTALL_MODULE_DESCRIPTION');
        $this->PARTNER_NAME = Loc::getMessage('OTUS_PRICES_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('OTUS_PRICES_PARTNER_URI');
    }
    
    /**
     * Установка модуля
     *
     * @return void
     * @throws \Bitrix\Main\SystemException
     */
    public function DoInstall(): void
    {
        if ($this->isVersionD7()) {
            ModuleManager::registerModule($this->MODULE_ID);
            $this->InstallDB();
            $this->InstallFiles();
            $this->InstallEvents();
        } else {
            throw new \Bitrix\Main\SystemException(
                Loc::getMessage('OTUS_PRICES_INSTALL_ERROR_VERSION')
            );
        }
    }

    /**
     * Установка базы данных
     *
     * @return void
     * @throws \Bitrix\Main\Db\SqlQueryException
     */
    public function InstallDB(): void
    {
        Loader::includeModule($this->MODULE_ID);
        ServicesTable::getEntity()->createDbTable();
    }

    /**
     * Удаление базы данных
     *
     * @return void
     */
    public function UninstallDB(): void
    {
        $connection = Application::getConnection();
        $tableName = 'b_otus_prices'; // Указываем имя таблицы напрямую
        if ($connection->isTableExists($tableName)) {
            $connection->dropTable($tableName);
        }
    }

    /**
     * Установка файлов
     *
     * @return void
     */
    public function InstallFiles(): void
    {
        CopyDirFiles(
            __DIR__ . '/components/',
            Application::getDocumentRoot() . '/local/components/',
            true,
            true
        );
    }

    /**
     * Удаление файлов
     *
     * @return void
     */
    public function UninstallFiles(): void
    {
        DeleteDirFilesEx('/local/components/otus/crm.prices.list/');
    }

    /**
     * Установка обработчиков событий
     *
     * @return void
     */
    public function InstallEvents(): void
    {
        $eventManager = EventManager::getInstance();
        $eventManager->registerEventHandler(
            'crm',
            'onEntityDetailsTabsInitialized',
            $this->MODULE_ID,
            '\Otus\Crm\Prices\EventHandlers\CrmTabHandler',
            'onEntityDetailsTabsInitialized'
        );
    }

    /**
     * Удаление обработчиков событий
     *
     * @return void
     */
    public function UninstallEvents(): void
    {
        $eventManager = EventManager::getInstance();
        $eventManager->unRegisterEventHandler(
            'crm',
            'onEntityDetailsTabsInitialized',
            $this->MODULE_ID,
            '\Otus\Crm\Prices\EventHandlers\CrmTabHandler',
            'onEntityDetailsTabsInitialized'
        );
    }

    /**
     * Удаление модуля
     *
     * @return void
     * @throws \Bitrix\Main\SystemException
     */
    public function DoUninstall(): void
    {
        $this->UninstallEvents();
        $this->UninstallFiles();
        $this->UninstallDB();
        ModuleManager::unRegisterModule($this->MODULE_ID);
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
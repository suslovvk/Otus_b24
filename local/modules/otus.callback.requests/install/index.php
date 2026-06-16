<?php

use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Otus\Callback\Requests\Orm\CallbackRequestTable;
use Otus\Callback\Requests\Rest\RestHandler;

Loc::loadMessages(__FILE__);

class otus_callback_requests extends CModule
{
    public $MODULE_ID = 'otus.callback.requests';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;

    /**
     * @return void
     */
    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('OTUS_CALLBACK_REQUESTS_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('OTUS_CALLBACK_REQUESTS_MODULE_DESCRIPTION');
    }

    /**
     * @return void
     * @throws \Bitrix\Main\LoaderException
     */
    public function DoInstall(): void
    {
        ModuleManager::registerModule($this->MODULE_ID);
        $this->installDb();
        $this->installEvents();
    }

    /**
     * @return void
     */
    public function DoUninstall(): void
    {
        $this->uninstallEvents();
        $this->uninstallDb();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    /**
     * @return void
     * @throws \Bitrix\Main\LoaderException
     */
    public function installDb(): void
    {
        \Bitrix\Main\Loader::includeModule($this->MODULE_ID);
        if (!CallbackRequestTable::getEntity()->getConnection()->isTableExists(CallbackRequestTable::getTableName())) {
            CallbackRequestTable::getEntity()->createDbTable();
        }
    }

    /**
     * @return void
     */
    public function uninstallDb(): void
    {
        $connection = Application::getConnection();
        $tableName = 'b_otus_callback_requests';
        if ($connection->isTableExists($tableName)) {
            $connection->dropTable($tableName);
        }
    }

    /**
     * @return void
     */
    public function installEvents(): void
    {
        EventManager::getInstance()->registerEventHandler(
            'rest',
            'OnRestServiceBuildDescription',
            $this->MODULE_ID,
            RestHandler::class,
            'onRestServiceBuildDescription'
        );
    }

    /**
     * @return void
     */
    public function uninstallEvents(): void
    {
        EventManager::getInstance()->unRegisterEventHandler(
            'rest',
            'OnRestServiceBuildDescription',
            $this->MODULE_ID,
            RestHandler::class,
            'onRestServiceBuildDescription'
        );
    }
}

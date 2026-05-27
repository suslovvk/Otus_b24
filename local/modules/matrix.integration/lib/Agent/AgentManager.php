<?php

namespace Matrix\Integration\Agent;

use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Loader;
use Matrix\Integration\Api\MatrixClient;
use Matrix\Integration\Sync\BonusSync;
use Matrix\Integration\Sync\ControlTurnoutSync;
use Matrix\Integration\Sync\DoctorSync;
use Matrix\Integration\Sync\PatientSync;
use Matrix\Integration\Sync\ProductSync;

class AgentManager
{
    private const MODULE_ID   = 'matrix.integration';
    private const AGENT_CLASS = '\Matrix\Integration\Agent\AgentManager::';
    private const LOG_FILE    = '/bitrix/php_interface/log/matrix_integration.log';

    /** Регистрирует все агенты при установке модуля. */
    public static function registerAll(): void
    {
        $agents = [
            ['method' => 'syncCatalogAgent',          'period' => 86400, 'time' => '02:00:00'],
            ['method' => 'checkZeroStockAgent',        'period' => 86400, 'time' => '02:15:00'],
            ['method' => 'syncDoctorsAgent',           'period' => 86400, 'time' => '02:30:00'],
            ['method' => 'syncPatientsAgent',          'period' => 86400, 'time' => '03:00:00'],
            ['method' => 'processTurnoutsAgent',       'period' => 86400, 'time' => '08:00:00'],
            ['method' => 'checkExpiringBonusesAgent',  'period' => 86400, 'time' => '09:00:00'],
        ];

        foreach ($agents as $agent) {
            $call = self::AGENT_CLASS . $agent['method'] . '();';

            \CAgent::RemoveAgent($call, self::MODULE_ID);

            \CAgent::AddAgent(
                $call,
                self::MODULE_ID,
                'Y',
                $agent['period'],
                '',
                'Y',
                date('d.m.Y ' . $agent['time'], strtotime('tomorrow'))
            );
        }
    }

    /** Снимает регистрацию всех агентов при удалении модуля. */
    public static function unregisterAll(): void
    {
        \CAgent::RemoveModuleAgents(self::MODULE_ID);
    }

    /** @return string следующий вызов агента (требование Битрикс) */
    public static function syncCatalogAgent(): string
    {
        try {
            Loader::includeModule(self::MODULE_ID);
            (new ProductSync(new MatrixClient()))->syncAll();
        } catch (\Throwable $e) {
            self::logError('syncCatalogAgent', $e);
        }

        return self::AGENT_CLASS . 'syncCatalogAgent();';
    }

    /** @return string */
    public static function checkZeroStockAgent(): string
    {
        try {
            Loader::includeModule(self::MODULE_ID);
            (new ProductSync(new MatrixClient()))->checkZeroStock();
        } catch (\Throwable $e) {
            self::logError('checkZeroStockAgent', $e);
        }

        return self::AGENT_CLASS . 'checkZeroStockAgent();';
    }

    /** @return string */
    public static function syncDoctorsAgent(): string
    {
        try {
            Loader::includeModule(self::MODULE_ID);
            (new DoctorSync(new MatrixClient()))->syncAll();
        } catch (\Throwable $e) {
            self::logError('syncDoctorsAgent', $e);
        }

        return self::AGENT_CLASS . 'syncDoctorsAgent();';
    }

    /** @return string */
    public static function syncPatientsAgent(): string
    {
        try {
            Loader::includeModule(self::MODULE_ID);
            (new PatientSync(new MatrixClient()))->syncAll();
        } catch (\Throwable $e) {
            self::logError('syncPatientsAgent', $e);
        }

        return self::AGENT_CLASS . 'syncPatientsAgent();';
    }

    /** @return string */
    public static function processTurnoutsAgent(): string
    {
        try {
            Loader::includeModule(self::MODULE_ID);
            (new ControlTurnoutSync(new MatrixClient()))->processTodayTurnouts();
        } catch (\Throwable $e) {
            self::logError('processTurnoutsAgent', $e);
        }

        return self::AGENT_CLASS . 'processTurnoutsAgent();';
    }

    /** @return string */
    public static function checkExpiringBonusesAgent(): string
    {
        try {
            Loader::includeModule(self::MODULE_ID);
            (new BonusSync(new MatrixClient()))->checkExpiringBonuses();
        } catch (\Throwable $e) {
            self::logError('checkExpiringBonusesAgent', $e);
        }

        return self::AGENT_CLASS . 'checkExpiringBonusesAgent();';
    }

    private static function logError(string $agent, \Throwable $e): void
    {
        Debug::writeToFile(
            ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
            '[AgentManager::' . $agent . ']',
            self::LOG_FILE
        );
    }
}

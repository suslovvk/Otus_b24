<?php

namespace Matrix\Integration\Sync;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Matrix\Integration\Api\MatrixClient;
use Matrix\Integration\Orm\BonusLogTable;
use Matrix\Integration\Orm\BonusTable;
use Matrix\Integration\Util\ApiItem;

/**
 * Синхронизирует бонусы и лог транзакций пациента из Matrix.
 *
 * @package Matrix\Integration\Sync
 */
class BonusSync
{
    private const MODULE_ID          = 'matrix.integration';
    private const LOG_FILE           = '/bitrix/php_interface/log/matrix_integration.log';
    private const DEFAULT_NOTIFY_DAYS = 7;

    private array $log = [];

    public function __construct(
        private readonly MatrixClient $client
    ) {}

    private function addLog(string $message): void
    {
        $this->log[] = $message;
        if (Option::get(self::MODULE_ID, 'MATRIX_DEBUG_MODE', 'N') === 'Y') {
            Debug::writeToFile(
                ['message' => $message, 'timestamp' => date('Y-m-d H:i:s')],
                '[BonusSync]',
                self::LOG_FILE
            );
        }
    }

    public function getLog(): array
    {
        return $this->log;
    }

    /**
     * Синхронизирует бонусный баланс и лог транзакций пациента.
     *
     * @param int $matrixPatientId  ID пациента в Matrix
     * @param int $bitrixContactId  ID контакта в Битрикс24
     */
    public function syncForPatient(int $matrixPatientId, int $bitrixContactId): void
    {
        $this->addLog("syncForPatient: Matrix patient ID={$matrixPatientId}");

        // Бонусный баланс
        try {
            $raw          = $this->client->get("patients/{$matrixPatientId}/bonus-balance");
            // API возвращает {"status":"success","data":300} — скаляр, обёрнутый в _scalar
            $bonusBalance = isset($raw['_scalar'])
                ? (float) $raw['_scalar']
                : (float) (ApiItem::float($raw, 0.0, 'bonus_balance', 'balance', 'amount') ?? 0.0);
            $this->upsertBonusBalance($matrixPatientId, $bitrixContactId, $bonusBalance);
            $this->addLog("Bonus balance сохранён: patient={$matrixPatientId}, balance={$bonusBalance}");
        } catch (\Throwable $e) {
            $this->addLog("Ошибка загрузки bonus-balance: " . $e->getMessage());
        }

        // Лог транзакций
        try {
            $logItems = $this->client->get("patients/{$matrixPatientId}/bonus-log");
            if (!is_array($logItems)) {
                $logItems = [];
            }
            foreach ($logItems as $item) {
                $this->upsertBonusLog($item, $matrixPatientId, $bitrixContactId);
            }
            $this->addLog("Bonus log синхронизирован: " . count($logItems) . " записей");
        } catch (\Throwable $e) {
            $this->addLog("Ошибка загрузки bonus-log: " . $e->getMessage());
        }
    }

    private function upsertBonusBalance(int $matrixPatientId, int $bitrixContactId, float $balance): void
    {
        $existing = BonusTable::getRow([
            'filter' => ['=MATRIX_PATIENT_ID' => $matrixPatientId],
            'select' => ['ID'],
        ]);

        $fields = [
            'MATRIX_PATIENT_ID' => $matrixPatientId,
            'BITRIX_CONTACT_ID' => $bitrixContactId,
            'BONUS_BALANCE'     => $balance,
        ];

        if ($existing) {
            BonusTable::update($existing['ID'], $fields);
        } else {
            BonusTable::add($fields);
        }
    }

    private function upsertBonusLog(array $item, int $matrixPatientId, int $bitrixContactId): void
    {
        $matrixLogId = ApiItem::int($item, 0, 'id', 'log_id');

        $existing = null;
        if ($matrixLogId > 0) {
            $existing = BonusLogTable::getRow([
                'filter' => ['=MATRIX_LOG_ID' => $matrixLogId],
                'select' => ['ID', 'NOTIFIED'],
            ]);
        }

        $fields = [
            'MATRIX_LOG_ID'     => $matrixLogId ?: null,
            'MATRIX_PATIENT_ID' => $matrixPatientId,
            'BITRIX_CONTACT_ID' => $bitrixContactId,
            'TYPE'              => ApiItem::string($item, '', 'type', 'operation_type'),
            'AMOUNT'            => ApiItem::float($item, null, 'amount', 'sum'),
            'BALANCE'           => ApiItem::float($item, null, 'balance', 'current_balance'),
            'STATUS'            => ApiItem::string($item, '', 'status', 'state'),
        ];

        // Парсим datetime-поля
        $dateFields = [
            'ACTIVATED_AT'   => ['activated_at', 'activate_at'],
            'DEACTIVATED_AT' => ['deactivated_at', 'deactivate_at', 'expires_at'],
        ];
        foreach ($dateFields as $field => $keys) {
            $val = ApiItem::string($item, '', ...$keys);
            if ($val !== '') {
                try {
                    $fields[$field] = DateTime::createFromPhp(new \DateTime($val));
                } catch (\Throwable) {}
            }
        }

        if ($existing) {
            // Не трогаем NOTIFIED при обновлении — флаг ставится только checkExpiringBonuses
            unset($fields['MATRIX_LOG_ID']);
            BonusLogTable::update($existing['ID'], $fields);
        } else {
            BonusLogTable::add($fields);
        }
    }

    /**
     * Начисляет бонусы пациенту через Matrix API.
     *
     * @param int    $matrixPatientId  ID пациента
     * @param int    $amount           Количество бонусов
     * @param string $activatedAt      Дата активации (Y-m-d H:i:s или ISO)
     * @param string $deactivatedAt    Дата сгорания
     * @param string $note             Комментарий
     * @throws \RuntimeException
     */
    public function chargeBonus(
        int $matrixPatientId,
        int $amount,
        string $activatedAt,
        string $deactivatedAt,
        string $note = ''
    ): void {
        $this->addLog("chargeBonus: patient={$matrixPatientId}, amount={$amount}");

        $body = [
            'amount'         => $amount,
            'activated_at'   => $activatedAt,
            'deactivated_at' => $deactivatedAt,
        ];
        if ($note !== '') {
            $body['note'] = $note;
        }

        $this->client->post("patients/{$matrixPatientId}/bonus-charge", $body);
        $this->addLog("chargeBonus: бонусы начислены patient={$matrixPatientId}");
    }

    /**
     * Проверяет лог бонусов на предстоящее сгорание.
     * Отправляет IM-уведомления менеджеру за N дней до deactivated_at.
     * N берётся из настройки MATRIX_BONUS_NOTIFY_DAYS (по умолчанию 7).
     */
    public function checkExpiringBonuses(): void
    {
        $this->addLog('checkExpiringBonuses: начало проверки');

        $notifyDays = (int) Option::get(self::MODULE_ID, 'MATRIX_BONUS_NOTIFY_DAYS', (string) self::DEFAULT_NOTIFY_DAYS);
        if ($notifyDays <= 0) {
            $notifyDays = self::DEFAULT_NOTIFY_DAYS;
        }

        $now       = new DateTime();
        $threshold = DateTime::createFromPhp(
            (new \DateTime("+{$notifyDays} days"))->setTime(23, 59, 59)
        );

        $expiring = BonusLogTable::getList([
            'filter' => [
                '=NOTIFIED'        => 0,
                '>=DEACTIVATED_AT' => $now,
                '<=DEACTIVATED_AT' => $threshold,
            ],
            'select' => ['ID', 'MATRIX_PATIENT_ID', 'BITRIX_CONTACT_ID', 'AMOUNT', 'DEACTIVATED_AT'],
        ])->fetchAll();

        $count = count($expiring);
        $this->addLog("checkExpiringBonuses: найдено {$count} записей на уведомление");

        if ($count === 0) {
            return;
        }

        $responsibleId = (int) Option::get(self::MODULE_ID, 'MATRIX_RESPONSIBLE_USER_ID', '1') ?: 1;

        $imAvailable = Loader::includeModule('im');
        if (!$imAvailable) {
            $this->addLog('Модуль im не доступен, уведомления не отправлены');
        }

        foreach ($expiring as $row) {
            $amount    = (float) ($row['AMOUNT'] ?? 0);
            $deactAt   = (string) ($row['DEACTIVATED_AT'] ?? '');
            $contactId = (int) ($row['BITRIX_CONTACT_ID'] ?? 0);

            if ($imAvailable) {
                $contactUrl = $contactId > 0 ? SITE_DIR . 'crm/contact/show/' . $contactId . '/' : '';
                $message    = "Сгорание бонусов через {$notifyDays} дн.: {$amount} бонусов"
                    . ($deactAt ? " (до {$deactAt})" : '')
                    . ($contactUrl ? " — {$contactUrl}" : '');

                \Bitrix\Im\Notify::Add([
                    'TO_USER_ID'   => $responsibleId,
                    'FROM_USER_ID' => 1,
                    'TYPE'         => \Bitrix\Im\Notify::SYSTEM,
                    'MESSAGE'      => $message,
                ]);
            }

            BonusLogTable::update((int) $row['ID'], ['NOTIFIED' => 1]);
            $this->addLog("Уведомление отправлено: bonus_log ID={$row['ID']}");
        }
    }
}

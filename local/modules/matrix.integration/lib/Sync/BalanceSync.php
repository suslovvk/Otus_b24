<?php

namespace Matrix\Integration\Sync;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Diag\Debug;
use Matrix\Integration\Api\MatrixClient;
use Matrix\Integration\Orm\BalanceTable;
use Matrix\Integration\Util\ApiItem;

/**
 * Синхронизирует баланс пациента из Matrix в ORM-таблицу matrix_balance.
 *
 * @package Matrix\Integration\Sync
 */
class BalanceSync
{
    private const MODULE_ID = 'matrix.integration';
    private const LOG_FILE  = '/bitrix/php_interface/log/matrix_integration.log';

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
                '[BalanceSync]',
                self::LOG_FILE
            );
        }
    }

    public function getLog(): array
    {
        return $this->log;
    }

    /**
     * Загружает и сохраняет баланс пациента.
     *
     * @param int $matrixPatientId  ID пациента в Matrix
     * @param int $bitrixContactId  ID контакта в Битрикс24
     */
    public function syncForPatient(int $matrixPatientId, int $bitrixContactId): void
    {
        $this->addLog("syncForPatient: Matrix patient ID={$matrixPatientId}");

        try {
            $raw = $this->client->get("patients/{$matrixPatientId}/balance");
            // API возвращает {"status":"success","data":65280} — скаляр, обёрнутый в _scalar
            $balance = isset($raw['_scalar'])
                ? (float) $raw['_scalar']
                : (float) (ApiItem::float($raw, 0.0, 'balance', 'amount', 'sum') ?? 0.0);

            $this->upsertBalance($matrixPatientId, $bitrixContactId, $balance);
            $this->addLog("Balance сохранён: patient={$matrixPatientId}, balance={$balance}");
        } catch (\Throwable $e) {
            $this->addLog("Ошибка syncForPatient: " . $e->getMessage());
        }
    }

    private function upsertBalance(int $matrixPatientId, int $bitrixContactId, float $balance): void
    {
        $existing = BalanceTable::getRow([
            'filter' => ['=MATRIX_PATIENT_ID' => $matrixPatientId],
            'select' => ['ID'],
        ]);

        $fields = [
            'MATRIX_PATIENT_ID' => $matrixPatientId,
            'BITRIX_CONTACT_ID' => $bitrixContactId,
            'BALANCE'           => $balance,
        ];

        if ($existing) {
            BalanceTable::update($existing['ID'], $fields);
        } else {
            BalanceTable::add($fields);
        }
    }

    /**
     * Пополняет баланс пациента через Matrix API.
     *
     * @param int    $matrixPatientId  ID пациента
     * @param float  $sum              Сумма пополнения
     * @param string $comment          Комментарий
     * @return array{balance:float, receiptId:int, receiptSum:float}
     * @throws \RuntimeException
     */
    public function replenishBalance(int $matrixPatientId, float $sum, string $comment = ''): array
    {
        $this->addLog("replenishBalance: patient={$matrixPatientId}, sum={$sum}");

        $body = ['sum' => $sum];
        if ($comment !== '') {
            $body['comment'] = $comment;
        }

        $response   = $this->client->put("patients/{$matrixPatientId}/balance", $body);
        $balance    = ApiItem::float($response, 0.0, 'balance', 'amount') ?? 0.0;
        $receiptId  = ApiItem::int($response, 0, 'receipt_id', 'receiptId');
        $receiptSum = ApiItem::float($response, 0.0, 'receipt_sum', 'receiptSum') ?? 0.0;

        $this->addLog("Balance пополнен: patient={$matrixPatientId}, new balance={$balance}");

        return [
            'balance'    => $balance,
            'receiptId'  => $receiptId,
            'receiptSum' => $receiptSum,
        ];
    }

    /**
     * Возвращает всех пациентов с отрицательным балансом.
     *
     * @return array Массив записей из matrix_balance где BALANCE < 0
     */
    public function getNegativeBalances(): array
    {
        return BalanceTable::getList([
            'filter' => ['<BALANCE' => 0],
            'select' => ['ID', 'MATRIX_PATIENT_ID', 'BITRIX_CONTACT_ID', 'BALANCE'],
        ])->fetchAll();
    }
}

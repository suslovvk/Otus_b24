<?php

namespace Matrix\Integration\Sync;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Type\DateTime;
use Matrix\Integration\Api\MatrixClient;
use Matrix\Integration\Orm\RecordTable;
use Matrix\Integration\Util\ApiItem;

/**
 * Синхронизирует записи на приём пациента из Matrix в ORM-таблицу matrix_records.
 *
 * Направление: Matrix → Битрикс24 (одностороннее)
 * Источники: GET /patients/{id}/records + GET /patients/{id}/online-records
 *
 * @package Matrix\Integration\Sync
 */
class RecordSync
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
                '[RecordSync]',
                self::LOG_FILE
            );
        }
    }

    public function getLog(): array
    {
        return $this->log;
    }

    /**
     * Загружает все записи пациента (обычные + онлайн) и сохраняет в ORM.
     *
     * @param int $matrixPatientId  ID пациента в Matrix
     * @param int $bitrixContactId  ID контакта в Битрикс24
     * @return array{total:int, created:int, updated:int, errors:list<string>}
     */
    public function syncForPatient(int $matrixPatientId, int $bitrixContactId): array
    {
        $this->addLog("=== syncForPatient: Matrix patient ID={$matrixPatientId} ===");

        $result = ['total' => 0, 'created' => 0, 'updated' => 0, 'errors' => []];

        // Обычные записи (paginate обходит все страницы, API отдаёт по 20)
        try {
            $count = 0;
            foreach ($this->client->paginate("patients/{$matrixPatientId}/records") as $record) {
                $action = $this->upsertRecord($record, $matrixPatientId, $bitrixContactId);
                $result['total']++;
                $count++;
                if ($action === 'created' || $action === 'updated') {
                    $result[$action]++;
                }
            }
            $this->addLog("Обычных записей загружено: {$count}");
        } catch (\Throwable $e) {
            $this->addLog("Ошибка загрузки records: " . $e->getMessage());
            $result['errors'][] = 'records: ' . $e->getMessage();
        }

        // Онлайн-записи (paginate обходит все страницы)
        try {
            $count = 0;
            foreach ($this->client->paginate("patients/{$matrixPatientId}/online-records") as $record) {
                $record['_is_online'] = true;
                $action = $this->upsertRecord($record, $matrixPatientId, $bitrixContactId);
                $result['total']++;
                $count++;
                if ($action === 'created' || $action === 'updated') {
                    $result[$action]++;
                }
            }
            $this->addLog("Онлайн-записей загружено: {$count}");
        } catch (\Throwable $e) {
            $this->addLog("Ошибка загрузки online-records: " . $e->getMessage());
            $result['errors'][] = 'online-records: ' . $e->getMessage();
        }

        $this->addLog("=== syncForPatient done: всего {$result['total']} ===");
        return $result;
    }

    /**
     * Создаёт или обновляет одну запись в ORM.
     *
     * @param array $recordData       Данные записи из Matrix API
     * @param int   $matrixPatientId  ID пациента в Matrix
     * @param int   $bitrixContactId  ID контакта в Битрикс24
     * @return string 'created'|'updated'|'error'
     */
    public function upsertRecord(array $recordData, int $matrixPatientId, int $bitrixContactId): string
    {
        $matrixRecordId = ApiItem::int($recordData, 0, 'id', 'record_id');
        if ($matrixRecordId <= 0) {
            $this->addLog("upsertRecord: пропуск — нет record_id");
            return 'error';
        }

        // Парсим datetime (формат Matrix: Y-m-d\TH:i:sP)
        $datetimeStr = ApiItem::string($recordData, '', 'datetime', 'date_time', 'time', 'scheduled_at');
        $datetime    = null;
        if ($datetimeStr !== '') {
            try {
                $datetime = DateTime::createFromPhp(new \DateTime($datetimeStr));
            } catch (\Throwable) {}
        }

        if ($datetime === null) {
            $this->addLog("upsertRecord: пропуск — не удалось распознать datetime для ID={$matrixRecordId}");
            return 'error';
        }

        // API возвращает doctor_id только в некоторых эндпоинтах; в /records его нет.
        $matrixDoctorId = ApiItem::int($recordData, 0, 'doctor_id', 'physician_id') ?: null;

        // ФИО врача из поля 'user' (строка, которую отдаёт /patients/{id}/records)
        $doctorName = ApiItem::string($recordData, '', 'user', 'doctor_name', 'doctor');

        // Название процедуры из поля 'procedure'
        $procedureName = ApiItem::string($recordData, '', 'procedure', 'procedure_name', 'service');

        // Ищем BITRIX_USER_ID по doctor map только если известен doctor_id
        $bitrixUserId = null;
        if ($matrixDoctorId > 0) {
            $map = json_decode((string) Option::get(self::MODULE_ID, 'DOCTOR_MAP', '{}'), true);
            $uid = (int) ($map[$matrixDoctorId] ?? 0);
            if ($uid > 0) {
                $bitrixUserId = $uid;
            }
        }

        $isOnline = ApiItem::bool($recordData, false, 'is_online', 'online')
            || (isset($recordData['_is_online']) && $recordData['_is_online']);

        $canceled = ApiItem::bool($recordData, false, 'canceled', 'is_canceled', 'cancelled');

        // API /records не возвращает поле status — выводим из canceled и datetime
        $status = ApiItem::string($recordData, '', 'status', 'state');
        if ($status === '') {
            if ($canceled) {
                $status = 'canceled';
            } elseif ($datetimeStr !== '') {
                try {
                    $status = (new \DateTime($datetimeStr)) < new \DateTime() ? 'completed' : 'scheduled';
                } catch (\Throwable) {}
            }
        }

        $fields = [
            'MATRIX_RECORD_ID'    => $matrixRecordId,
            'MATRIX_PATIENT_ID'   => $matrixPatientId,
            'BITRIX_CONTACT_ID'   => $bitrixContactId,
            'MATRIX_DOCTOR_ID'    => $matrixDoctorId,
            'BITRIX_USER_ID'      => $bitrixUserId,
            'DOCTOR_NAME'         => $doctorName !== '' ? $doctorName : null,
            'MATRIX_PROCEDURE_ID' => ApiItem::int($recordData, 0, 'procedure_id', 'service_id') ?: null,
            'PROCEDURE_NAME'      => $procedureName !== '' ? $procedureName : null,
            'DATETIME'            => $datetime,
            'STATUS'              => $status !== '' ? $status : null,
            'CANCELED'            => $canceled ? 1 : 0,
            'IS_ONLINE'           => $isOnline ? 1 : 0,
        ];

        $existing = RecordTable::getRow([
            'filter' => ['=MATRIX_RECORD_ID' => $matrixRecordId],
            'select' => ['ID'],
        ]);

        if ($existing) {
            RecordTable::update($existing['ID'], $fields);
            $this->addLog("Record обновлён: Matrix ID={$matrixRecordId}");
            return 'updated';
        }

        RecordTable::add($fields);
        $this->addLog("Record создан: Matrix ID={$matrixRecordId}");
        return 'created';
    }
}

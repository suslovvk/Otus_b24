<?php

namespace Matrix\Integration\Sync;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Crm\FieldMultiTable;
use Bitrix\Crm\ContactTable;
use CCrmContact;
use Matrix\Integration\Api\MatrixClient;

/**
 * Синхронизация пациентов Matrix → контакты Битрикс24.
 * Итерирует /patients/{id} с остановкой после 50 последовательных 404.
 */
class PatientSync
{
    private const MODULE_ID = 'matrix.integration';
    private const STOP_AFTER_EMPTY = 50;
    private const LOG_FILE = '/bitrix/php_interface/log/matrix_integration.log';

    private array $log = [];

    public function __construct(
        private readonly MatrixClient $client
    ) {
    }

    private function addLog(string $message): void
    {
        $this->log[] = $message;
        
        $debugMode = Option::get(self::MODULE_ID, 'MATRIX_DEBUG_MODE', 'N') === 'Y';
        if ($debugMode) {
            Debug::writeToFile(
                ['message' => $message, 'timestamp' => date('Y-m-d H:i:s')],
                '[PatientSync]',
                self::LOG_FILE
            );
        }
    }

    public function getLog(): array
    {
        return $this->log;
    }

    private function isCrmAvailable(): bool
    {
        return Loader::includeModule('crm');
    }

    private function normalizePhone(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Если номер начинается с 8 и длина 11, заменяем 8 на +7
        if (strlen($phone) === 11 && strpos($phone, '8') === 0) {
            $phone = '+7' . substr($phone, 1);
        }
        
        // Если номер начинается с 9 и длина 10, добавляем +7
        if (strlen($phone) === 10 && strpos($phone, '9') === 0) {
            $phone = '+7' . $phone;
        }
        
        return $phone;
    }

    private function saveMultiFields(int $contactId, array $phones, array $emails): void
    {
        $oldPhones = FieldMultiTable::getList([
            'filter' => [
                'ENTITY_ID' => 'CONTACT',
                'ELEMENT_ID' => $contactId,
                'TYPE_ID' => 'PHONE',
            ],
        ])->fetchAll();
        
        foreach ($oldPhones as $item) {
            FieldMultiTable::delete($item['ID']);
        }

        foreach ($phones as $phone) {
            $value = $this->normalizePhone($phone['VALUE']);
            if (empty($value)) {
                continue;
            }
            
            FieldMultiTable::add([
                'ENTITY_ID' => 'CONTACT',
                'ELEMENT_ID' => $contactId,
                'TYPE_ID' => 'PHONE',
                'VALUE_TYPE' => $phone['VALUE_TYPE'],
                'VALUE' => $value,
                'COMPLEX_ID' => 'PHONE_' . $phone['VALUE_TYPE'],
            ]);
        }
        
        $oldEmails = FieldMultiTable::getList([
            'filter' => [
                'ENTITY_ID' => 'CONTACT',
                'ELEMENT_ID' => $contactId,
                'TYPE_ID' => 'EMAIL',
            ],
        ])->fetchAll();
        
        foreach ($oldEmails as $item) {
            FieldMultiTable::delete($item['ID']);
        }

        foreach ($emails as $email) {
            if (empty($email['VALUE'])) {
                continue;
            }
            
            FieldMultiTable::add([
                'ENTITY_ID' => 'CONTACT',
                'ELEMENT_ID' => $contactId,
                'TYPE_ID' => 'EMAIL',
                'VALUE_TYPE' => $email['VALUE_TYPE'],
                'VALUE' => $email['VALUE'],
                'COMPLEX_ID' => 'EMAIL_' . $email['VALUE_TYPE'],
            ]);
        }
        
        // Обновляем флаги HAS_PHONE и HAS_EMAIL
        $hasPhone = !empty($phones) ? 'Y' : 'N';
        $hasEmail = !empty($emails) ? 'Y' : 'N';
        
        $connection = \Bitrix\Main\Application::getConnection();
        $helper     = $connection->getSqlHelper();
        $connection->query(
            "UPDATE b_crm_contact SET HAS_PHONE = '" . $helper->forSql($hasPhone) . "'," .
            " HAS_EMAIL = '" . $helper->forSql($hasEmail) . "' WHERE ID = " . (int) $contactId
        );
    }

    /**
     * Полная итеративная выгрузка всех пациентов.
     *
     * @return array{total:int, created:int, updated:int, skipped:int, errors:list<string>}
     */
    public function syncAll(): array
    {
        $this->addLog('=== НАЧАЛО syncAll (полная выгрузка пациентов) ===');
        
        $result = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        if (!$this->isCrmAvailable()) {
            $result['errors'][] = 'Модуль CRM не установлен';
            $this->addLog('ОШИБКА: модуль CRM не установлен');
            return $result;
        }

        $emptyCount = 0;
        $patientId = 1;
        $maxPatientId = (int) Option::get(self::MODULE_ID, 'MATRIX_PATIENTS_MAX_ID', '10000');
        if ($maxPatientId <= 0) {
            $maxPatientId = 10000;
        }

        while ($emptyCount < self::STOP_AFTER_EMPTY && $patientId <= $maxPatientId) {
            $this->addLog("Проверка пациента ID={$patientId}");
            
            try {
                $response = $this->client->get('patients/' . $patientId);

                if ($response && isset($response['id'])) {
                    $emptyCount = 0;
                    $syncResult = $this->syncOne($response);
                    if ($syncResult['action'] === 'created') {
                        $result['created']++;
                    } elseif ($syncResult['action'] === 'updated') {
                        $result['updated']++;
                    } else {
                        $result['skipped']++;
                    }
                    $result['total']++;
                } else {
                    $emptyCount++;
                }
            } catch (\Throwable $e) {
                $emptyCount++;
                $this->addLog("Ошибка пациента ID={$patientId}: " . $e->getMessage());
            }
            
            $patientId++;
        }

        $this->addLog("=== КОНЕЦ syncAll: обработано {$result['total']} пациентов ===");
        
        return $result;
    }

    /**
     * Синхронизирует одного пациента по данным из Matrix.
     *
     * @param array $patientData Данные пациента из Matrix API
     * @return array{action:string, contact_id:int, matrix_patient_id:int}
     */
    public function syncOne(array $patientData): array
    {
        $result = [
            'action' => 'none',
            'contact_id' => 0,
            'matrix_patient_id' => 0,
        ];

        $matrixPatientId = (int) ($patientData['id'] ?? 0);
        if ($matrixPatientId <= 0) {
            $this->addLog('ОШИБКА: некорректный ID пациента');
            return $result;
        }
        $result['matrix_patient_id'] = $matrixPatientId;

        if (!$this->isCrmAvailable()) {
            $this->addLog("ОШИБКА: модуль CRM не доступен для пациента ID={$matrixPatientId}");
            return $result;
        }

        $existingContact = $this->findContactByMatrixId($matrixPatientId);
        $contactFields   = $this->buildContactFields($patientData);
        $phones          = $this->buildPhonesArray($patientData);
        $emails          = $this->buildEmailsArray($patientData);

        if ($existingContact) {
            $contactId = (int) $existingContact['ID'];
            $this->updateContact($contactId, $contactFields);
            $this->saveMultiFields($contactId, $phones, $emails);

            $result['action'] = 'updated';
            $result['contact_id'] = $contactId;
            $this->addLog("Обновлён контакт: Matrix ID={$matrixPatientId}, Contact ID={$contactId}");
        } else {
            $contactId = $this->createContact($contactFields);
            if ($contactId > 0) {
                $this->saveMultiFields($contactId, $phones, $emails);
                
                $result['action'] = 'created';
                $result['contact_id'] = $contactId;
                $this->addLog("Создан контакт: Matrix ID={$matrixPatientId}, Contact ID={$contactId}");
            } else {
                $this->addLog("ОШИБКА создания контакта: Matrix ID={$matrixPatientId}");
            }
        }

        return $result;
    }

    /**
     * Синхронизирует пачку пациентов для чанкового AJAX-обхода.
     *
     * Предназначен для вызова из JS-петли: каждый батч обрабатывает $batchSize ID подряд,
     * передаёт счётчик пустых ответов следующему батчу и сигнализирует об окончании через `finished`.
     *
     * @param int $startId        Первый Matrix-ID в батче
     * @param int $batchSize      Кол-во ID в батче (10–200)
     * @param int $prevEmptyCount Счётчик последовательных 404, накопленный до батча
     * @param int $toId           Верхняя граница диапазона (0 — использовать MATRIX_PATIENTS_MAX_ID)
     * @return array{next_id:int, empty_count:int, created:int, updated:int, skipped:int, total:int, errors:list<string>, finished:bool, max_id:int}
     */
    public function syncBatch(int $startId, int $batchSize = 100, int $prevEmptyCount = 0, int $toId = 0): array
    {
        $maxPatientId = (int) Option::get(self::MODULE_ID, 'MATRIX_PATIENTS_MAX_ID', '10000');
        if ($maxPatientId <= 0) {
            $maxPatientId = 10000;
        }

        // Если передан явный верхний ID, используем его (но не выше MATRIX_PATIENTS_MAX_ID)
        $effectiveMax = ($toId > 0) ? min($toId, $maxPatientId) : $maxPatientId;

        $result = [
            'next_id'     => $startId,
            'empty_count' => $prevEmptyCount,
            'created'     => 0,
            'updated'     => 0,
            'skipped'     => 0,
            'total'       => 0,
            'errors'      => [],
            'finished'    => false,
            'max_id'      => $effectiveMax,
        ];

        if (!$this->isCrmAvailable()) {
            $result['errors'][] = 'Модуль CRM не установлен';
            $result['finished'] = true;
            return $result;
        }

        $endId      = min($startId + $batchSize - 1, $effectiveMax);
        $emptyCount = $prevEmptyCount;
        $patientId  = $startId;

        while ($patientId <= $endId && $emptyCount < self::STOP_AFTER_EMPTY) {
            try {
                $response = $this->client->get('patients/' . $patientId);

                if ($response && isset($response['id'])) {
                    $emptyCount     = 0;
                    $syncResult     = $this->syncOne($response);
                    match ($syncResult['action']) {
                        'created' => $result['created']++,
                        'updated' => $result['updated']++,
                        default   => $result['skipped']++,
                    };
                    $result['total']++;
                } else {
                    $emptyCount++;
                }
            } catch (\Throwable $e) {
                $emptyCount++;
                if (count($result['errors']) < 10) {
                    $result['errors'][] = "ID {$patientId}: " . $e->getMessage();
                }
            }

            $patientId++;
        }

        $result['next_id']     = $patientId;
        $result['empty_count'] = $emptyCount;
        $result['finished']    = $emptyCount >= self::STOP_AFTER_EMPTY || $patientId > $effectiveMax;

        return $result;
    }

    /**
     * Синхронизирует одного пациента по его Matrix ID.
     */
    public function syncById(int $matrixPatientId): array
    {
        $this->addLog("syncById: запрос пациента Matrix ID={$matrixPatientId}");
        
        try {
            $response = $this->client->get('patients/' . $matrixPatientId);
            
            if (!$response || !isset($response['id'])) {
                $this->addLog("syncById: пациент с ID={$matrixPatientId} не найден");
                return [
                    'action' => 'none',
                    'contact_id' => 0,
                    'matrix_patient_id' => $matrixPatientId,
                ];
            }
            
            return $this->syncOne($response);
            
        } catch (\Throwable $e) {
            $this->addLog("syncById: ошибка: " . $e->getMessage());
            return [
                'action' => 'none',
                'contact_id' => 0,
                'matrix_patient_id' => $matrixPatientId,
            ];
        }
    }

    private function findContactByMatrixId(int $matrixPatientId): ?array
    {
        $contact = ContactTable::getList([
            'filter' => ['=UF_MATRIX_PATIENT_ID' => $matrixPatientId],
            'select' => ['ID'],
            'limit' => 1,
        ])->fetch();
        
        return $contact ?: null;
    }

    private function buildPhonesArray(array $patientData): array
    {
        $phones = [];
        
        $contactPhone = $patientData['contact_phone'] ?? '';
        if (!empty($contactPhone)) {
            $phones[] = [
                'VALUE' => $contactPhone,
                'VALUE_TYPE' => 'WORK',
            ];
        }
        
        $homePhone = $patientData['home_phone'] ?? '';
        if (!empty($homePhone)) {
            $phones[] = [
                'VALUE' => $homePhone,
                'VALUE_TYPE' => 'HOME',
            ];
        }
        
        $parentPhone = $patientData['parent_phone'] ?? '';
        if (!empty($parentPhone)) {
            $phones[] = [
                'VALUE' => $parentPhone,
                'VALUE_TYPE' => 'OTHER',
            ];
        }
        
        return $phones;
    }

    private function buildEmailsArray(array $patientData): array
    {
        $emails = [];
        
        $email = $patientData['email'] ?? '';
        if (!empty($email)) {
            $emails[] = [
                'VALUE' => $email,
                'VALUE_TYPE' => 'WORK',
            ];
        }
        
        return $emails;
    }

    private function buildContactFields(array $patientData): array
    {
        $fullName = $patientData['full_name'] ?? '';
        $parsedName = $this->parseFullName($fullName);
        
        $fields = [
            'NAME' => $parsedName['first'],
            'LAST_NAME' => $parsedName['last'],
            'SECOND_NAME' => $parsedName['second'],
            'UF_MATRIX_PATIENT_ID' => (int) $patientData['id'],
            'UF_MATRIX_SYNCED_AT' => new DateTime(),
        ];
        
        return $fields;
    }

    private function parseFullName(string $fullName): array
    {
        $result = [
            'last' => '',
            'first' => '',
            'second' => '',
        ];
        
        if ($fullName === '') {
            return $result;
        }
        
        $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
        
        if (count($parts) >= 1) {
            $result['last'] = $parts[0];
        }
        if (count($parts) >= 2) {
            $result['first'] = $parts[1];
        }
        if (count($parts) >= 3) {
            $result['second'] = $parts[2];
        }
        
        return $result;
    }

    private function createContact(array $fields): int
    {
        $contact       = new CCrmContact();
        $responsibleId = (int) Option::get(self::MODULE_ID, 'MATRIX_RESPONSIBLE_USER_ID', '1');
        if ($responsibleId > 0 && !isset($fields['ASSIGNED_BY_ID'])) {
            $fields['ASSIGNED_BY_ID'] = $responsibleId;
        }
        
        $contactId = $contact->Add($fields);
        
        if ($contactId <= 0) {
            $this->addLog("createContact: ОШИБКА - " . ($contact->LAST_ERROR ?: 'неизвестная'));
            return 0;
        }
        
        return (int) $contactId;
    }

    private function updateContact(int $contactId, array $fields): bool
    {
        if (empty($fields['NAME'])) {
            unset($fields['NAME']);
        }
        if (empty($fields['LAST_NAME'])) {
            unset($fields['LAST_NAME']);
        }
        if (empty($fields['SECOND_NAME'])) {
            unset($fields['SECOND_NAME']);
        }
        
        $contact = new CCrmContact();
        $success = $contact->Update($contactId, $fields);
        
        if (!$success) {
            $this->addLog("updateContact: ОШИБКА ID={$contactId}: " . ($contact->LAST_ERROR ?: 'неизвестная'));
            return false;
        }
        
        return true;
    }
}
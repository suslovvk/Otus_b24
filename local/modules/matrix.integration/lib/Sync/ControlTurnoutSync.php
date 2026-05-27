<?php

namespace Matrix\Integration\Sync;

use Bitrix\Crm\ContactTable;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\Date;
use Matrix\Integration\Api\MatrixClient;
use Matrix\Integration\Orm\ControlTurnoutTable;
use Matrix\Integration\Util\ApiItem;

/**
 * Синхронизирует контрольные явки пациента из Matrix.
 * При наступлении даты явки создаёт Дело (тип Встреча) в CRM и уведомляет врача.
 *
 * @package Matrix\Integration\Sync
 */
class ControlTurnoutSync
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
                '[ControlTurnoutSync]',
                self::LOG_FILE
            );
        }
    }

    public function getLog(): array
    {
        return $this->log;
    }

    /**
     * Загружает и сохраняет контрольные явки пациента.
     *
     * @param int $matrixPatientId  ID пациента в Matrix
     * @param int $bitrixContactId  ID контакта в Битрикс24
     * @return array{total:int, created:int, updated:int, errors:list<string>}
     */
    public function syncForPatient(int $matrixPatientId, int $bitrixContactId): array
    {
        $this->addLog("syncForPatient: Matrix patient ID={$matrixPatientId}");

        $result = ['total' => 0, 'created' => 0, 'updated' => 0, 'errors' => []];

        try {
            $turnouts = $this->client->get("patients/{$matrixPatientId}/control-turnouts");
            if (!is_array($turnouts)) {
                $turnouts = [];
            }

            foreach ($turnouts as $turnout) {
                $action = $this->upsertTurnout($turnout, $matrixPatientId, $bitrixContactId);
                $result['total']++;
                if ($action === 'created') {
                    $result['created']++;
                } elseif ($action === 'updated') {
                    $result['updated']++;
                }
            }

            $this->addLog("syncForPatient done: {$result['total']} turnouts");
        } catch (\Throwable $e) {
            $this->addLog("Ошибка syncForPatient: " . $e->getMessage());
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    private function upsertTurnout(array $data, int $matrixPatientId, int $bitrixContactId): string
    {
        $matrixId = ApiItem::int($data, 0, 'id', 'turnout_id', 'control_turnout_id');
        if ($matrixId <= 0) {
            $this->addLog("upsertTurnout: пропуск — нет ID");
            return 'error';
        }

        // API отдаёт дату в формате d.m.Y ("26.09.2024"), не в ISO-формате
        $dateStr = ApiItem::string($data, '', 'date', 'turnout_date', 'scheduled_date');
        $date    = null;
        if ($dateStr !== '') {
            try {
                $dtPhp = \DateTime::createFromFormat('d.m.Y', $dateStr)
                    ?: \DateTime::createFromFormat('Y-m-d', $dateStr)
                    ?: new \DateTime($dateStr);
                $date = Date::createFromPhp($dtPhp);
            } catch (\Throwable) {}
        }

        if ($date === null) {
            $this->addLog("upsertTurnout: пропуск — не удалось распознать дату '{$dateStr}' для ID={$matrixId}");
            return 'error';
        }

        // API отдаёт имя врача строкой в поле 'doctor', doctor_id отсутствует
        $doctorName     = ApiItem::string($data, '', 'doctor', 'doctor_name', 'user');
        $matrixDoctorId = ApiItem::int($data, 0, 'doctor_id', 'physician_id') ?: null;

        // Пытаемся разрешить BITRIX_USER_ID через doctor map (если doctor_id всё же есть)
        $bitrixUserId = null;
        if ($matrixDoctorId > 0) {
            $map = json_decode((string) Option::get(self::MODULE_ID, 'DOCTOR_MAP', '{}'), true);
            $uid = (int) ($map[$matrixDoctorId] ?? 0);
            if ($uid > 0) {
                $bitrixUserId = $uid;
            }
        }

        $fields = [
            'MATRIX_ID'         => $matrixId,
            'MATRIX_PATIENT_ID' => $matrixPatientId,
            'BITRIX_CONTACT_ID' => $bitrixContactId,
            'MATRIX_DOCTOR_ID'  => $matrixDoctorId,
            'BITRIX_USER_ID'    => $bitrixUserId,
            'DOCTOR_NAME'       => $doctorName !== '' ? $doctorName : null,
            'TURNOUT_DATE'      => $date,
        ];

        $existing = ControlTurnoutTable::getRow([
            'filter' => ['=MATRIX_ID' => $matrixId],
            'select' => ['ID', 'NOTIFIED'],
        ]);

        if ($existing) {
            // Не сбрасываем NOTIFIED и BITRIX_ACTIVITY_ID при обновлении
            ControlTurnoutTable::update($existing['ID'], $fields);
            return 'updated';
        }

        ControlTurnoutTable::add($fields);
        return 'created';
    }

    /**
     * Проверяет таблицу на явки с датой = сегодня (NOTIFIED = 0).
     * Для каждой создаёт Дело (тип Встреча) в CRM и уведомляет врача.
     *
     * @return int Количество обработанных явок
     */
    public function processTodayTurnouts(): int
    {
        $this->addLog('processTodayTurnouts: начало обработки');

        if (!Loader::includeModule('crm')) {
            $this->addLog('Модуль CRM не доступен');
            return 0;
        }

        $today    = new Date();
        $turnouts = ControlTurnoutTable::getList([
            'filter' => [
                '=NOTIFIED'     => 0,
                '=TURNOUT_DATE' => $today,
            ],
            'select' => ['ID', 'MATRIX_ID', 'BITRIX_CONTACT_ID', 'BITRIX_USER_ID', 'TURNOUT_DATE'],
        ])->fetchAll();

        $this->addLog("Найдено явок на сегодня: " . count($turnouts));

        $defaultResponsible = (int) Option::get(self::MODULE_ID, 'MATRIX_RESPONSIBLE_USER_ID', '1') ?: 1;
        $processed          = 0;

        foreach ($turnouts as $turnout) {
            $contactId     = (int) ($turnout['BITRIX_CONTACT_ID'] ?? 0);
            $responsibleId = (int) ($turnout['BITRIX_USER_ID'] ?? 0) ?: $defaultResponsible;
            $dateStr       = (string) ($turnout['TURNOUT_DATE'] ?? date('Y-m-d'));

            if ($contactId <= 0) {
                $this->addLog("Пропуск явки ID={$turnout['ID']}: нет контакта");
                continue;
            }

            $patientName = $this->getContactName($contactId);

            try {
                $activityId = $this->createActivity($contactId, $responsibleId, $patientName, $dateStr);

                ControlTurnoutTable::update((int) $turnout['ID'], [
                    'NOTIFIED'           => 1,
                    'BITRIX_ACTIVITY_ID' => $activityId,
                ]);

                $this->addLog("Явка обработана: ID={$turnout['ID']}, Activity ID={$activityId}");
                $processed++;
            } catch (\Throwable $e) {
                $this->addLog("Ошибка для явки ID={$turnout['ID']}: " . $e->getMessage());
            }
        }

        $this->addLog("processTodayTurnouts: обработано {$processed} явок");
        return $processed;
    }

    private function getContactName(int $contactId): string
    {
        try {
            $contact = ContactTable::getRow([
                'filter' => ['=ID' => $contactId],
                'select' => ['NAME', 'LAST_NAME', 'SECOND_NAME'],
            ]);
            if ($contact) {
                return trim(implode(' ', array_filter([
                    $contact['LAST_NAME'] ?? '',
                    $contact['NAME'] ?? '',
                    $contact['SECOND_NAME'] ?? '',
                ])));
            }
        } catch (\Throwable) {}

        return 'Пациент #' . $contactId;
    }

    /**
     * Создаёт дело-напоминание в CRM Битрикс24.
     *
     * @param int    $contactId     ID контакта (пациент)
     * @param int    $responsibleId ID ответственного (врач)
     * @param string $patientName   ФИО пациента
     * @param string $date          Дата явки (Y-m-d или d.m.Y)
     * @return int ID созданного дела
     * @throws \RuntimeException
     */
    public function createActivityForTurnout(int $turnoutId): int
    {
        $turnout = ControlTurnoutTable::getRow([
            'filter' => ['=ID' => $turnoutId],
            'select' => ['ID', 'BITRIX_CONTACT_ID', 'BITRIX_USER_ID', 'DOCTOR_NAME', 'TURNOUT_DATE', 'BITRIX_ACTIVITY_ID'],
        ]);

        if (!$turnout) {
            throw new \RuntimeException("Контрольная явка ID={$turnoutId} не найдена");
        }

        if ((int) $turnout['BITRIX_ACTIVITY_ID'] > 0) {
            return (int) $turnout['BITRIX_ACTIVITY_ID'];
        }

        $contactId     = (int) $turnout['BITRIX_CONTACT_ID'];
        $responsibleId = (int) ($turnout['BITRIX_USER_ID'] ?? 0)
            ?: (int) Option::get(self::MODULE_ID, 'MATRIX_RESPONSIBLE_USER_ID', '1') ?: 1;
        $patientName   = $this->getContactName($contactId);

        $dateObj = $turnout['TURNOUT_DATE'];
        $dateStr = ($dateObj instanceof \Bitrix\Main\Type\Date) ? $dateObj->format('Y-m-d') : (string) $dateObj;

        $activityId = $this->createActivity($contactId, $responsibleId, $patientName, $dateStr);

        ControlTurnoutTable::update($turnoutId, [
            'BITRIX_ACTIVITY_ID' => $activityId,
            'NOTIFIED'           => 1,
        ]);

        return $activityId;
    }

    public function createActivity(int $contactId, int $responsibleId, string $patientName, string $date): int
    {
        $subject          = "Контрольная явка: {$patientName}";
        $startTimestamp   = strtotime($date . ' 09:00:00') ?: time();
        $deadlineTimestamp = $startTimestamp + 1800; // +30 минут

        $activityId = (int) \CCrmActivity::Add([
            'OWNER_TYPE_ID'  => \CCrmOwnerType::Contact,
            'OWNER_ID'       => $contactId,
            'TYPE_ID'        => \CCrmActivityType::Meeting,
            'SUBJECT'        => $subject,
            'DESCRIPTION'    => "Контрольная явка пациента {$patientName} на {$date}",
            'START_TIME'     => ConvertTimeStamp($startTimestamp, 'FULL'),
            'DEADLINE'       => ConvertTimeStamp($deadlineTimestamp, 'FULL'),
            'RESPONSIBLE_ID' => $responsibleId,
            'COMPLETED'      => 'N',
            'NOTIFY_TYPE'    => 1,
            'NOTIFY_VALUE'   => 15,
        ]);

        if ($activityId <= 0) {
            throw new \RuntimeException("Не удалось создать дело для контакта ID={$contactId}");
        }

        // IM-уведомление врачу
        if (Loader::includeModule('im')) {
            $contactUrl = SITE_DIR . 'crm/contact/show/' . $contactId . '/';
            \Bitrix\Im\Notify::Add([
                'TO_USER_ID'   => $responsibleId,
                'FROM_USER_ID' => 1,
                'TYPE'         => \Bitrix\Im\Notify::SYSTEM,
                'MESSAGE'      => "Контрольная явка сегодня: {$patientName} — {$contactUrl}",
            ]);
        }

        return $activityId;
    }
}

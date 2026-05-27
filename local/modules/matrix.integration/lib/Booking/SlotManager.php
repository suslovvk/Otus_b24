<?php

namespace Matrix\Integration\Booking;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Diag\Debug;
use Matrix\Integration\Api\MatrixClient;
use Matrix\Integration\Orm\ProcedureTable;
use Matrix\Integration\Util\ApiItem;

/**
 * Управляет подбором свободных слотов и созданием/отменой записей на приём.
 *
 * Слот = 15 минут. Процедура занимает duration/15 слотов подряд.
 *
 * @package Matrix\Integration\Booking
 */
class SlotManager
{
    private const MODULE_ID      = 'matrix.integration';
    private const LOG_FILE       = '/bitrix/php_interface/log/matrix_integration.log';
    private const SLOT_MINUTES   = 15;

    public function __construct(
        private readonly MatrixClient $client
    ) {}

    private function addLog(string $message): void
    {
        if (Option::get(self::MODULE_ID, 'MATRIX_DEBUG_MODE', 'N') === 'Y') {
            Debug::writeToFile(
                ['message' => $message, 'timestamp' => date('Y-m-d H:i:s')],
                '[SlotManager]',
                self::LOG_FILE
            );
        }
    }

    /**
     * Возвращает доступные слоты на один день.
     *
     * @param int    $matrixDoctorId    ID врача в Matrix
     * @param int    $matrixProcedureId ID процедуры в Matrix
     * @param string $date              Дата в формате d.m.Y или Y-m-d
     * @return array Массив слотов вида [['time' => 'HH:MM', 'datetime' => 'ATOM'], ...]
     */
    public function getAvailableSlots(int $matrixDoctorId, int $matrixProcedureId, string $date): array
    {
        $this->addLog("getAvailableSlots: doctor={$matrixDoctorId}, procedure={$matrixProcedureId}, date={$date}");

        $duration    = $this->getProcedureDuration($matrixProcedureId);
        $slotsNeeded = max(1, (int) ceil($duration / self::SLOT_MINUTES));

        $apiDate  = $this->toApiDate($date);
        $schedule = $this->client->get('schedule', [
            'doctor'    => $matrixDoctorId,
            'procedure' => $matrixProcedureId,
            'date'      => $apiDate,
        ]);

        $freeSlots = $this->extractFreeSlots($schedule);
        $this->addLog("Свободных слотов: " . count($freeSlots) . ", нужно подряд: {$slotsNeeded}");

        if ($slotsNeeded > 1) {
            $freeSlots = $this->findConsecutiveSlots($freeSlots, $slotsNeeded);
        }

        return $this->formatSlots($freeSlots);
    }

    /**
     * Возвращает доступные слоты за период (до 14 дней с startDate), сгруппированные по дням.
     * Делает отдельный API-запрос на каждый день, т.к. range-запрос /schedule возвращает
     * только текущий день вне зависимости от date_from/date_to.
     *
     * @param int    $matrixDoctorId    ID врача в Matrix
     * @param int    $matrixProcedureId ID процедуры в Matrix
     * @param string $startDate         Начало периода (Y-m-d), по умолч. сегодня
     * @param int    $days              Количество дней для проверки (макс 21)
     * @return array Массив вида ['Y-m-d' => [['time' => 'HH:MM', 'datetime' => 'ATOM'], ...]]
     */
    public function getMonthSlots(int $matrixDoctorId, int $matrixProcedureId, string $startDate = '', int $days = 14, int $overrideDuration = 0): array
    {
        $tz       = new \DateTimeZone('+05:00');
        $today    = new \DateTime('now', $tz);
        $start    = ($startDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate))
            ? new \DateTime($startDate, $tz)
            : clone $today;

        // Не показываем прошедшие даты
        if ($start < $today) {
            $start = clone $today;
        }

        $days        = min(max(1, $days), 21);
        $duration    = $overrideDuration > 0 ? $overrideDuration : $this->getProcedureDuration($matrixProcedureId);
        $slotsNeeded = max(1, (int) ceil($duration / self::SLOT_MINUTES));

        $this->addLog("getMonthSlots: doctor={$matrixDoctorId}, procedure={$matrixProcedureId}, start={$start->format('Y-m-d')}, days={$days}");

        $byDay   = [];
        $current = clone $start;

        for ($i = 0; $i < $days; $i++) {
            $dateStr = $current->format('d.m.Y');

            try {
                $schedule  = $this->client->get('schedule', [
                    'doctor'    => $matrixDoctorId,
                    'procedure' => $matrixProcedureId,
                    'date'      => $dateStr,
                ]);
                $freeSlots = $this->extractFreeSlots($schedule);

                if ($slotsNeeded > 1) {
                    $freeSlots = $this->findConsecutiveSlots($freeSlots, $slotsNeeded);
                }

                if (!empty($freeSlots)) {
                    $day = $current->format('Y-m-d');
                    foreach ($freeSlots as $slotDt) {
                        try {
                            $dt            = (new \DateTimeImmutable($slotDt))->setTimezone($tz);
                            $byDay[$day][] = ['time' => $dt->format('H:i'), 'datetime' => $dt->format(\DateTime::ATOM)];
                        } catch (\Throwable) {}
                    }
                }
            } catch (\Throwable $e) {
                $this->addLog("getMonthSlots: error for {$dateStr}: " . $e->getMessage());
            }

            $current->modify('+1 day');
        }

        return $byDay;
    }

    private function getProcedureDuration(int $matrixProcedureId): int
    {
        $row = ProcedureTable::getRow([
            'filter' => ['=MATRIX_ID' => $matrixProcedureId],
            'select' => ['DURATION'],
        ]);

        return $row ? (int) $row['DURATION'] : self::SLOT_MINUTES;
    }

    /**
     * Конвертирует дату в формат d.m.Y (формат Matrix API).
     */
    private function toApiDate(string $date): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            try {
                return (new \DateTime($date))->format('d.m.Y');
            } catch (\Throwable) {}
        }

        return $date;
    }

    private function extractFreeSlots(array $schedule): array
    {
        $free = [];

        // Основной формат: {doctors_plan: [{plan: {main: [datetime, ...]}}]}
        // 'main' = свободные слоты; 'duplicated' = все рабочие слоты (включая занятые)
        $doctorsPlan = $schedule['doctors_plan'] ?? [];
        if (is_array($doctorsPlan) && !empty($doctorsPlan)) {
            foreach ($doctorsPlan as $entry) {
                foreach ((array) ($entry['plan']['main'] ?? []) as $dt) {
                    if (is_string($dt) && $dt !== '') {
                        $free[] = $dt;
                    }
                }
            }
            return array_values(array_unique($free));
        }

        // Фолбэк для других структур ответа
        $items = $schedule['slots'] ?? $schedule['schedule'] ?? $schedule;
        if (!is_array($items)) {
            return [];
        }
        foreach ($items as $slot) {
            if (!is_array($slot)) {
                $free[] = (string) $slot;
                continue;
            }
            if (!ApiItem::bool($slot, true, 'free', 'is_free', 'available')) {
                continue;
            }
            $dt = ApiItem::string($slot, '', 'datetime', 'time', 'start_time', 'date_time');
            if ($dt !== '') {
                $free[] = $dt;
            }
        }

        return $free;
    }

    /**
     * Преобразует массив datetime-строк в формат [{time, datetime}].
     */
    private function formatSlots(array $datetimes): array
    {
        $result = [];
        foreach ($datetimes as $dt) {
            try {
                $d = new \DateTimeImmutable($dt);
                $result[] = ['time' => $d->format('H:i'), 'datetime' => $dt];
            } catch (\Throwable) {}
        }
        return $result;
    }

    /**
     * Находит группы подряд идущих свободных слотов нужной длины.
     *
     * @param array $allSlots    Все свободные слоты (массив datetime-строк)
     * @param int   $slotsNeeded Количество слотов подряд
     * @return array Массив datetime начала подходящих окон в формате ATOM
     */
    private function findConsecutiveSlots(array $allSlots, int $slotsNeeded): array
    {
        if (empty($allSlots) || $slotsNeeded <= 1) {
            return $allSlots;
        }

        // Парсим и сортируем по timestamp
        $tsToSlot = [];
        foreach ($allSlots as $slot) {
            try {
                $ts = (new \DateTimeImmutable($slot))->getTimestamp();
                $tsToSlot[$ts] = $slot;
            } catch (\Throwable) {
                continue;
            }
        }

        ksort($tsToSlot);
        $keys  = array_keys($tsToSlot);
        $step  = self::SLOT_MINUTES * 60;
        $count = count($keys);
        $result = [];

        for ($i = 0; $i <= $count - $slotsNeeded; $i++) {
            $consecutive = true;
            for ($j = 1; $j < $slotsNeeded; $j++) {
                if ($keys[$i + $j] - $keys[$i + $j - 1] !== $step) {
                    $consecutive = false;
                    break;
                }
            }
            if ($consecutive) {
                $result[] = (new \DateTime('@' . $keys[$i]))->format(\DateTime::ATOM);
            }
        }

        return $result;
    }

    /**
     * Создаёт запись на приём в Matrix через API.
     *
     * @param int    $matrixPatientId  ID пациента в Matrix
     * @param int    $matrixDoctorId   ID врача в Matrix
     * @param int    $matrixProcedureId ID процедуры
     * @param string $datetime         Дата и время начала в формате ATOM
     * @return array{record_id:int, patient_id:int}
     * @throws \RuntimeException
     */
    public function createRecord(
        int $matrixPatientId,
        int $matrixDoctorId,
        int $matrixProcedureId,
        string $datetime,
        int $overrideDuration = 0
    ): array {
        $this->addLog("createRecord: patient={$matrixPatientId}, doctor={$matrixDoctorId}, procedure={$matrixProcedureId}, datetime={$datetime}");

        $duration = $overrideDuration > 0 ? $overrideDuration : $this->getProcedureDuration($matrixProcedureId);
        try {
            $tz      = new \DateTimeZone('+05:00');
            $dtStart = (new \DateTimeImmutable($datetime))->setTimezone($tz);
            $dtEnd   = $dtStart->modify("+{$duration} minutes");
        } catch (\Throwable) {
            throw new \RuntimeException("Некорректный формат datetime: {$datetime}");
        }

        $response = $this->client->post('create-record', [
            'patient_id' => $matrixPatientId,
            'doctor'     => $matrixDoctorId,
            'procedure'  => $matrixProcedureId,
            'date'       => $dtStart->format(\DateTime::ATOM),
            'date_end'   => $dtEnd->format(\DateTime::ATOM),
        ]);

        $recordId  = ApiItem::int($response, 0, 'record_id', 'id', 'appointment_id');
        $patientId = ApiItem::int($response, $matrixPatientId, 'patient_id');

        $this->addLog("Запись создана: record_id={$recordId}");

        return [
            'record_id'  => $recordId,
            'patient_id' => $patientId,
        ];
    }

    /**
     * Отменяет запись на приём в Matrix.
     *
     * @param int $matrixPatientId  ID пациента
     * @param int $matrixRecordId   ID записи
     * @throws \RuntimeException
     */
    public function cancelRecord(int $matrixPatientId, int $matrixRecordId): void
    {
        $this->addLog("cancelRecord: patient={$matrixPatientId}, record={$matrixRecordId}");

        $this->client->put("patients/{$matrixPatientId}/records/{$matrixRecordId}/cancel");

        $this->addLog("Запись отменена: record_id={$matrixRecordId}");
    }
}

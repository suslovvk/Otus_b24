<?php

/**
 * AJAX-обработчик для вкладок Matrix CRM в карточке контакта.
 *
 * Все get_* действия читают из локальной БД (ORM-таблицы).
 * sync_patient — загружает свежие данные из Matrix API и обновляет БД.
 * Действия, требующие реального времени (get_slots, create_record,
 * replenish_balance, charge_bonus, cancel_record) обращаются к API напрямую.
 */

define('STOP_STATISTICS', true);
define('NO_KEEP_STATISTIC', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;
use Bitrix\Main\Web\Json;
use Matrix\Integration\Api\MatrixClient;
use Matrix\Integration\Booking\SlotManager;
use Matrix\Integration\Orm\BalanceTable;
use Matrix\Integration\Orm\BonusLogTable;
use Matrix\Integration\Orm\BonusTable;
use Matrix\Integration\Orm\ControlTurnoutTable;
use Matrix\Integration\Orm\ProcedureTable;
use Matrix\Integration\Orm\ReceiptTable;
use Matrix\Integration\Orm\RecordTable;
use Matrix\Integration\Sync\BalanceSync;
use Matrix\Integration\Sync\BonusSync;
use Matrix\Integration\Sync\ControlTurnoutSync;
use Matrix\Integration\Sync\ReceiptSync;
use Matrix\Integration\Sync\RecordSync;

header('Content-Type: application/json; charset=UTF-8');

$exit = static function (array $data): void {
    echo Json::encode($data);
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
    exit;
};

// ── Авторизация ──────────────────────────────────────────────────────────────

global $USER, $APPLICATION;

if (
    !$USER->IsAuthorized()
    || !Loader::includeModule('matrix.integration')
    || !Loader::includeModule('crm')
    || !check_bitrix_sessid()
) {
    $exit(['success' => false, 'message' => 'Access denied']);
}

$request         = \Bitrix\Main\Context::getCurrent()->getRequest();
$action          = (string) $request->getPost('action');
$contactId       = (int) $request->getPost('contact_id');
$matrixPatientId = (int) $request->getPost('patient_id');

// ── Роутинг ──────────────────────────────────────────────────────────────────

try {
    switch ($action) {

        // ── Баланс и бонусы: читаем из БД ────────────────────────────────────

        case 'get_balance':
            $balanceRow = BalanceTable::getRow([
                'filter' => ['=BITRIX_CONTACT_ID' => $contactId],
                'select' => ['BALANCE', 'DATE_UPDATE'],
            ]);
            $bonusRow = BonusTable::getRow([
                'filter' => ['=BITRIX_CONTACT_ID' => $contactId],
                'select' => ['BONUS_BALANCE', 'DATE_UPDATE'],
            ]);
            $exit([
                'success'       => true,
                'balance'       => $balanceRow ? (float) $balanceRow['BALANCE'] : null,
                'bonus_balance' => $bonusRow   ? (float) $bonusRow['BONUS_BALANCE'] : null,
                'updated_at'    => $balanceRow ? (string) $balanceRow['DATE_UPDATE'] : '',
            ]);

        case 'get_bonus_log':
            $page  = max(1, (int) $request->getPost('page'));
            $limit = max(1, min(50, (int) ($request->getPost('limit') ?: 15)));

            $allowedSort = ['ACTIVATED_AT', 'AMOUNT', 'DEACTIVATED_AT'];
            $sortBy  = in_array($request->getPost('sort'), $allowedSort, true)
                ? (string) $request->getPost('sort') : 'ACTIVATED_AT';
            $sortDir = strtoupper((string) $request->getPost('sort_dir')) === 'ASC' ? 'ASC' : 'DESC';

            $total = BonusLogTable::getCount(['=BITRIX_CONTACT_ID' => $contactId]);

            $rows = BonusLogTable::getList([
                'filter' => ['=BITRIX_CONTACT_ID' => $contactId],
                'select' => ['AMOUNT', 'ACTIVATED_AT', 'DEACTIVATED_AT', 'STATUS'],
                'order'  => [$sortBy => $sortDir],
                'limit'  => $limit,
                'offset' => ($page - 1) * $limit,
            ])->fetchAll();

            $now   = time();
            $items = [];
            foreach ($rows as $row) {
                $deactAtObj   = $row['DEACTIVATED_AT'];
                $actAtObj     = $row['ACTIVATED_AT'];
                $storedStatus = strtolower(trim((string) ($row['STATUS'] ?? '')));

                if ($storedStatus !== '') {
                    $lower    = mb_strtolower($storedStatus, 'UTF-8');
                    $isActive = str_starts_with($lower, 'актив')
                        || in_array($lower, ['active', 'y', '1'], true);
                } elseif ($deactAtObj instanceof \Bitrix\Main\Type\Date) {
                    $isActive = $deactAtObj->getTimestamp() > $now;
                } else {
                    $isActive = true;
                }

                $items[] = [
                    'created_at'     => ($actAtObj instanceof \Bitrix\Main\Type\Date)
                        ? $actAtObj->format('d.m.Y H:i:s') : '',
                    'amount'         => (float) ($row['AMOUNT'] ?? 0),
                    'deactivated_at' => ($deactAtObj instanceof \Bitrix\Main\Type\Date)
                        ? $deactAtObj->format('d.m.Y H:i:s') : '',
                    'is_active'      => $isActive,
                ];
            }
            $exit(['success' => true, 'items' => $items, 'total' => (int) $total]);

        // ── Пополнение баланса: обращается к API ──────────────────────────────

        case 'replenish_balance':
            $sum     = (float) $request->getPost('sum');
            $comment = (string) $request->getPost('comment');
            if ($sum <= 0) {
                $exit(['success' => false, 'message' => 'Укажите сумму больше 0']);
            }
            $result = (new BalanceSync(new MatrixClient()))->replenishBalance($matrixPatientId, $sum, $comment);
            $exit(['success' => true, 'message' => 'Баланс пополнен', 'balance' => $result['balance'] ?? null]);

        // ── Начисление бонусов: обращается к API ─────────────────────────────

        case 'charge_bonus':
            $amount = (int) $request->getPost('amount');
            $note   = (string) ($request->getPost('note') ?: $request->getPost('comment'));
            if ($amount <= 0) {
                $exit(['success' => false, 'message' => 'Укажите сумму бонусов']);
            }
            $activatedAtRaw = trim((string) $request->getPost('activated_at'));
            $deactAtRaw     = trim((string) $request->getPost('deactivated_at'));
            $activatedAt = ($activatedAtRaw !== '' && strtotime($activatedAtRaw) !== false)
                ? date('Y-m-d H:i:s', strtotime($activatedAtRaw))
                : date('Y-m-d H:i:s');
            $deactAt = ($deactAtRaw !== '' && strtotime($deactAtRaw) !== false)
                ? date('Y-m-d H:i:s', strtotime($deactAtRaw))
                : date('Y-m-d H:i:s', strtotime('+30 days'));
            (new BonusSync(new MatrixClient()))->chargeBonus($matrixPatientId, $amount, $activatedAt, $deactAt, $note);
            $exit(['success' => true, 'message' => 'Бонусы начислены']);

        // ── Записи на приём: читаем из БД ────────────────────────────────────

        case 'get_records':
            $page  = max(1, (int) $request->getPost('page'));
            $limit = 15;

            $total = RecordTable::getList([
                'filter' => ['=BITRIX_CONTACT_ID' => $contactId],
                'count_total' => true,
            ])->getCount();

            $rows = RecordTable::getList([
                'filter' => ['=BITRIX_CONTACT_ID' => $contactId],
                'select' => ['MATRIX_RECORD_ID', 'DATETIME', 'STATUS', 'CANCELED', 'IS_ONLINE',
                             'DOCTOR_NAME', 'PROCEDURE_NAME'],
                'order'  => ['DATETIME' => 'DESC'],
                'limit'  => $limit,
                'offset' => ($page - 1) * $limit,
            ])->fetchAll();

            $items = [];
            foreach ($rows as $row) {
                $canceled = (int) ($row['CANCELED'] ?? 0);
                $status   = strtolower((string) ($row['STATUS'] ?? ''));
                if ($status === '' && $canceled) {
                    $status = 'canceled';
                }
                $items[] = [
                    'matrix_record_id' => (int) $row['MATRIX_RECORD_ID'],
                    'appointment_at'   => ($row['DATETIME'] instanceof \Bitrix\Main\Type\DateTime)
                        ? (new \DateTimeImmutable('@' . $row['DATETIME']->getTimestamp()))
                            ->setTimezone(new \DateTimeZone('+05:00'))
                            ->format('d.m.Y H:i:s')
                        : (string) $row['DATETIME'],
                    'doctor_name'      => (string) ($row['DOCTOR_NAME'] ?? ''),
                    'procedure_name'   => (string) ($row['PROCEDURE_NAME'] ?? ''),
                    'status'           => $status,
                    'is_online'        => (bool) ($row['IS_ONLINE'] ?? false),
                ];
            }

            $exit(['success' => true, 'items' => $items, 'total' => (int) $total]);

        // ── Отмена записи: обращается к API, затем обновляет БД ──────────────

        case 'cancel_record':
            $recordId = (int) $request->getPost('matrix_record_id');
            if ($recordId <= 0) {
                $exit(['success' => false, 'message' => 'Не указан ID записи']);
            }
            (new SlotManager(new MatrixClient()))->cancelRecord($matrixPatientId, $recordId);
            $rec = RecordTable::getRow([
                'filter' => ['=MATRIX_RECORD_ID' => $recordId],
                'select' => ['ID'],
            ]);
            if ($rec) {
                RecordTable::update((int) $rec['ID'], ['CANCELED' => 1, 'STATUS' => 'canceled']);
            }
            $exit(['success' => true, 'message' => 'Запись отменена']);

        // ── Форма новой записи: врачи и процедуры из БД, слоты из API ────────

        case 'get_doctors':
            $raw   = (new MatrixClient())->get('doctors');
            $list  = $raw['data'] ?? $raw['items'] ?? $raw['doctors'] ?? [];
            if (!is_array($list) || empty($list)) {
                $list = is_array($raw) && isset(array_values($raw)[0]) && is_array(array_values($raw)[0])
                    ? array_values($raw) : [];
            }
            $items = [];
            foreach ($list as $doc) {
                if (!is_array($doc)) {
                    continue;
                }
                $id = (int) ($doc['id'] ?? $doc['doctor_id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $name = trim((string) ($doc['full_name'] ?? $doc['name'] ?? ''));
                if ($name === '') {
                    $name = 'Врач #' . $id;
                }
                $items[] = ['matrix_id' => $id, 'full_name' => $name];
            }
            usort($items, static fn($a, $b) => strcmp($a['full_name'], $b['full_name']));
            $exit(['success' => true, 'items' => $items]);

        case 'get_doctor_specialties':
        case 'get_procedures':
            $matrixDoctorId = (int) $request->getPost('matrix_doctor_id');
            $specialtyId    = (int) $request->getPost('specialty_id');

            if (!$matrixDoctorId) {
                $exit(['success' => false, 'message' => 'Не указан врач']);
            }

            // Ищем врача в списке /doctors (paginate останавливается как только врач найден)
            $doc    = null;
            $client = new MatrixClient();
            foreach ($client->paginate('doctors') as $d) {
                if (is_array($d) && (int) ($d['id'] ?? 0) === $matrixDoctorId) {
                    $doc = $d;
                    break;
                }
            }

            if ($action === 'get_doctor_specialties') {
                $seen  = [];
                $items = [];
                foreach ((array) ($doc['procedures'] ?? []) as $proc) {
                    foreach ((array) ($proc['specialties'] ?? []) as $spec) {
                        $specId = (int) ($spec['id'] ?? 0);
                        if ($specId > 0 && !isset($seen[$specId])) {
                            $seen[$specId] = true;
                            $items[]       = [
                                'id'   => $specId,
                                'name' => (string) ($spec['name'] ?? 'Специальность #' . $specId),
                            ];
                        }
                    }
                }
                usort($items, static fn($a, $b) => strcmp($a['name'], $b['name']));
                $exit(['success' => true, 'items' => $items]);
            }

            // get_procedures: строим индекс локальных процедур (duration/cost)
            $localIndex = [];
            foreach (ProcedureTable::getList([
                'select' => ['MATRIX_ID', 'NAME', 'DURATION', 'COST'],
            ])->fetchAll() as $lp) {
                $localIndex[(int) $lp['MATRIX_ID']] = $lp;
            }

            $items = [];
            foreach ((array) ($doc['procedures'] ?? []) as $proc) {
                $procId = (int) ($proc['id'] ?? 0);
                if ($procId <= 0) {
                    continue;
                }

                if ($specialtyId > 0) {
                    $hasSpec = false;
                    foreach ((array) ($proc['specialties'] ?? []) as $spec) {
                        if ((int) ($spec['id'] ?? 0) === $specialtyId) {
                            $hasSpec = true;
                            break;
                        }
                    }
                    if (!$hasSpec) {
                        continue;
                    }
                }

                $local   = $localIndex[$procId] ?? null;
                $items[] = [
                    'matrix_id' => $procId,
                    'name'      => $local
                        ? (string) $local['NAME']
                        : (string) ($proc['name'] ?? $proc['title'] ?? 'Процедура #' . $procId),
                    'duration'  => $local ? (int) $local['DURATION'] : (int) ($proc['duration'] ?? 30),
                    'cost'      => $local ? (float) $local['COST']   : (float) ($proc['price'] ?? $proc['cost'] ?? 0),
                ];
            }
            usort($items, static fn($a, $b) => strcmp($a['name'], $b['name']));
            $exit(['success' => true, 'items' => $items]);

        case 'get_slots':
            $matrixDoctorId    = (int) $request->getPost('matrix_doctor_id');
            $matrixProcedureId = (int) $request->getPost('matrix_procedure_id');
            $startDate         = (string) $request->getPost('start_date');
            $days              = max(1, min(21, (int) ($request->getPost('days') ?: 14)));
            $procDuration      = max(0, (int) $request->getPost('duration'));
            if (!$matrixDoctorId || !$matrixProcedureId) {
                $exit(['success' => false, 'message' => 'Укажите врача и процедуру']);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
                $startDate = date('Y-m-d');
            }
            $slotsByDay = (new SlotManager(new MatrixClient()))->getMonthSlots(
                $matrixDoctorId, $matrixProcedureId, $startDate, $days, $procDuration
            );
            // Вычисляем следующий период
            $nextStart = date('Y-m-d', strtotime($startDate . ' +' . $days . ' days'));
            $exit(['success' => true, 'start_date' => $startDate, 'next_start' => $nextStart, 'slots_by_day' => $slotsByDay]);

        case 'create_record':
            $matrixDoctorId    = (int) $request->getPost('matrix_doctor_id');
            $matrixProcedureId = (int) $request->getPost('matrix_procedure_id');
            $datetime          = (string) $request->getPost('datetime');
            $procDuration      = max(0, (int) $request->getPost('duration'));
            if (!$matrixDoctorId || !$matrixProcedureId || !$datetime) {
                $exit(['success' => false, 'message' => 'Не все параметры указаны']);
            }
            $client = new MatrixClient();
            $result = (new SlotManager($client))->createRecord($matrixPatientId, $matrixDoctorId, $matrixProcedureId, $datetime, $procDuration);
            if (($result['record_id'] ?? 0) > 0) {
                (new RecordSync($client))->syncForPatient($matrixPatientId, $contactId);
            }
            $exit(['success' => true, 'message' => 'Запись создана', 'record_id' => $result['record_id'] ?? 0]);

        case 'create_turnout_activity':
            $turnoutId = (int) $request->getPost('turnout_id');
            if ($turnoutId <= 0) {
                $exit(['success' => false, 'message' => 'Не указан ID явки']);
            }
            try {
                $activityId = (new ControlTurnoutSync(new MatrixClient()))->createActivityForTurnout($turnoutId);
                $exit(['success' => true, 'activity_id' => $activityId]);
            } catch (\RuntimeException $e) {
                $exit(['success' => false, 'message' => $e->getMessage()]);
            }

        // ── Контрольные явки: читаем из БД ───────────────────────────────────

        case 'get_turnouts':
            $page  = max(1, (int) $request->getPost('page'));
            $limit = 15;
            $rows  = ControlTurnoutTable::getList([
                'filter' => ['=BITRIX_CONTACT_ID' => $contactId],
                'select' => ['MATRIX_ID', 'TURNOUT_DATE', 'BITRIX_USER_ID', 'NOTIFIED', 'BITRIX_ACTIVITY_ID'],
                'order'  => ['TURNOUT_DATE' => 'DESC'],
                'limit'  => $limit,
                'offset' => ($page - 1) * $limit,
            ])->fetchAll();

            $userIds = array_filter(array_unique(array_column($rows, 'BITRIX_USER_ID')));
            $userMap = [];
            if ($userIds) {
                foreach (UserTable::getList([
                    'filter' => ['@ID' => $userIds],
                    'select' => ['ID', 'NAME', 'LAST_NAME'],
                ])->fetchAll() as $u) {
                    $userMap[(int) $u['ID']] = trim($u['LAST_NAME'] . ' ' . $u['NAME']);
                }
            }

            $items = [];
            foreach ($rows as $row) {
                $dateObj = $row['TURNOUT_DATE'];
                $items[] = [
                    'turnout_date'       => ($dateObj instanceof \Bitrix\Main\Type\Date)
                        ? $dateObj->format('d.m.Y')
                        : (string) $dateObj,
                    'doctor_name'        => $userMap[(int) $row['BITRIX_USER_ID']] ?? '',
                    'notified'           => (int) $row['NOTIFIED'] === 1,
                    'bitrix_activity_id' => (int) $row['BITRIX_ACTIVITY_ID'],
                ];
            }
            $exit(['success' => true, 'items' => $items]);

        // ── Квитанции: читаем из БД ───────────────────────────────────────────

        case 'get_receipts':
            $page  = max(1, (int) $request->getPost('page'));
            $limit = 15;

            $total = ReceiptTable::getList([
                'filter'      => ['=BITRIX_CONTACT_ID' => $contactId],
                'count_total' => true,
            ])->getCount();

            $rows = ReceiptTable::getList([
                'filter' => ['=BITRIX_CONTACT_ID' => $contactId],
                'select' => ['MATRIX_RECEIPT_ID', 'SUM', 'CHECKED_AT', 'BITRIX_DEAL_ID', 'ITEMS'],
                'order'  => ['CHECKED_AT' => 'DESC'],
                'limit'  => $limit,
                'offset' => ($page - 1) * $limit,
            ])->fetchAll();

            $items = [];
            foreach ($rows as $row) {
                $receiptItems = is_string($row['ITEMS']) ? (json_decode($row['ITEMS'], true) ?? []) : [];
                $items[] = [
                    'created_at'     => (string) $row['CHECKED_AT'],
                    'total_amount'   => (float) $row['SUM'],
                    'items'          => is_array($receiptItems) ? $receiptItems : [],
                    'bitrix_deal_id' => (int) $row['BITRIX_DEAL_ID'],
                ];
            }
            $exit(['success' => true, 'items' => $items, 'total' => (int) $total]);

        // ── Точечная синхронизация баланса и бонусов ─────────────────────────

        case 'sync_balance':
            if ($matrixPatientId <= 0 || $contactId <= 0) {
                $exit(['success' => false, 'message' => 'Не указан пациент']);
            }
            (new BalanceSync(new MatrixClient()))->syncForPatient($matrixPatientId, $contactId);
            $exit(['success' => true]);

        case 'sync_bonus':
            if ($matrixPatientId <= 0 || $contactId <= 0) {
                $exit(['success' => false, 'message' => 'Не указан пациент']);
            }
            (new BonusSync(new MatrixClient()))->syncForPatient($matrixPatientId, $contactId);
            $exit(['success' => true]);

        // ── Синхронизация пациента из Matrix API → БД ────────────────────────

        case 'sync_patient':
            if ($matrixPatientId <= 0 || $contactId <= 0) {
                $exit(['success' => false, 'message' => 'Не указан пациент']);
            }
            $client = new MatrixClient();
            (new RecordSync($client))->syncForPatient($matrixPatientId, $contactId);
            (new BalanceSync($client))->syncForPatient($matrixPatientId, $contactId);
            (new BonusSync($client))->syncForPatient($matrixPatientId, $contactId);
            (new ControlTurnoutSync($client))->syncForPatient($matrixPatientId, $contactId);
            (new ReceiptSync($client))->syncForPatient($matrixPatientId, $contactId);
            $exit(['success' => true, 'message' => 'Данные обновлены из Matrix CRM']);

        default:
            $exit(['success' => false, 'message' => 'Неизвестный action: ' . htmlspecialcharsbx($action)]);
    }

} catch (\Throwable $e) {
    $exit(['success' => false, 'message' => $e->getMessage(), 'trace' => $e->getFile() . ':' . $e->getLine()]);
}

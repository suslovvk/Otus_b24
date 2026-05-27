<?php

// /local/modules/matrix.integration/admin/ajax.php

define('STOP_STATISTICS', true);
define('NO_KEEP_STATISTIC', true);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\Json;
use Matrix\Integration\Api\MatrixClient;
use Matrix\Integration\Sync\DoctorSync;
use Matrix\Integration\Sync\PatientSync;
use Matrix\Integration\Sync\ProductSync;

header('Content-Type: application/json; charset=UTF-8');

/**
 * Отдаёт JSON-ответ и завершает выполнение скрипта.
 *
 * @param array $payload Данные для кодирования в JSON
 * @return never
 */
$jsonExit = static function (array $payload): void {
    echo Json::encode($payload);
    require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
    exit;
};

// ── Проверка доступа ─────────────────────────────────────────────────────────

if (
    !Loader::includeModule('matrix.integration')
    || !($USER instanceof CUser)
    || !$USER->IsAdmin()
    || !check_bitrix_sessid()
) {
    $jsonExit(['success' => false, 'message' => 'Access denied']);
}

// ── Роутинг по action ────────────────────────────────────────────────────────

try {
    $action = (string) ($_POST['action'] ?? '');

    switch ($action) {

        // ── Проверка соединения с Matrix API ─────────────────────────────────
        case 'test_connection':

            $ok = (new MatrixClient())->ping();
            $jsonExit([
                'success' => $ok,
                'message' => $ok
                    ? 'API Matrix доступен'
                    : 'Ошибка соединения. Проверьте URL и API-ключ в настройках.',
            ]);

        // ── Синхронизация каталога ────────────────────────────────────────────
        case 'sync_catalog':

            if (!Loader::includeModule('iblock') || !Loader::includeModule('catalog')) {
                throw new \RuntimeException('Требуются модули iblock и catalog');
            }

            $iblockId = (int) Option::get('matrix.integration', 'MATRIX_CATALOG_IBLOCK_ID', 0);
            if ($iblockId === 0) {
                throw new \RuntimeException('Инфоблок каталога не выбран в настройках (вкладка «Каталог»)');
            }

            $client = new MatrixClient();
            $sync   = new ProductSync($client, $iblockId);
            $sync->syncAll();

            $jsonExit([
                'success' => true,
                'message' => 'Синхронизация каталога завершена. Подробности в лог-файле.',
            ]);

        // ── Синхронизация процедур ────────────────────────────────────────────
        case 'sync_procedures':

            if (!Loader::includeModule('iblock') || !Loader::includeModule('catalog')) {
                throw new \RuntimeException('Требуются модули iblock и catalog');
            }

            $iblockId = (int) Option::get('matrix.integration', 'MATRIX_CATALOG_IBLOCK_ID', 0);
            if ($iblockId === 0) {
                throw new \RuntimeException('Инфоблок каталога не выбран в настройках (вкладка «Каталог»)');
            }

            $client = new MatrixClient();
            $sync   = new ProductSync($client, $iblockId);
            $sync->syncProcedures();

            $jsonExit([
                'success' => true,
                'message' => 'Синхронизация процедур завершена. Подробности в лог-файле.',
            ]);

        // ── Синхронизация врачей ──────────────────────────────────────────────
        case 'sync_doctors':

            $client = new MatrixClient();
            $sync   = new DoctorSync($client);
            $sync->syncAll();

            $jsonExit([
                'success' => true,
                'message' => 'Синхронизация врачей завершена. Подробности в лог-файле.',
            ]);

        // ── Полная выгрузка пациентов (устаревший, единый запрос) ────────────
        case 'sync_patients':

            if (!Loader::includeModule('crm')) {
                throw new \RuntimeException('Требуется модуль crm');
            }

            $client = new MatrixClient();
            $sync   = new PatientSync($client);
            $sync->syncAll();

            Option::set('matrix.integration', 'MATRIX_PATIENTS_LAST_SYNC', date('d.m.Y H:i:s'));

            $jsonExit([
                'success' => true,
                'message' => 'Выгрузка пациентов завершена. Подробности в лог-файле.',
            ]);

        // ── Чанковая выгрузка пациентов (один батч из JS-петли) ──────────────
        case 'sync_patients_batch':

            if (!Loader::includeModule('crm')) {
                throw new \RuntimeException('Требуется модуль crm');
            }

            set_time_limit(90);

            $startId    = max(1, (int) ($_POST['start_id']    ?? 1));
            $batchSize  = max(5, min(30, (int) ($_POST['batch_size']  ?? 20)));
            $emptyCount = max(0, (int) ($_POST['empty_count'] ?? 0));
            $toId       = max(0, (int) ($_POST['to_id']       ?? 0));

            $sync = new PatientSync(new MatrixClient());
            $data = $sync->syncBatch($startId, $batchSize, $emptyCount, $toId);

            if ($data['finished']) {
                Option::set('matrix.integration', 'MATRIX_PATIENTS_LAST_SYNC', date('d.m.Y H:i:s'));
                Option::set('matrix.integration', 'MATRIX_PATIENTS_SYNC_RESUME', '');
            } else {
                Option::set('matrix.integration', 'MATRIX_PATIENTS_SYNC_RESUME', json_encode([
                    'next_id'     => $data['next_id'],
                    'empty_count' => $data['empty_count'],
                    'saved_at'    => date('d.m.Y H:i:s'),
                ]));
            }

            $jsonExit(['success' => true, 'data' => $data]);

        // ── Список складов Matrix ─────────────────────────────────────────────
        case 'get_stores':
            $raw  = (new MatrixClient())->get('stores');
            $list = $raw['items'] ?? $raw['data'] ?? (array_values($raw) ?: []);
            $items = [];
            foreach ($list as $s) {
                if (!is_array($s)) {
                    continue;
                }
                $id = (int) ($s['id'] ?? $s['store_id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $items[] = [
                    'id'   => $id,
                    'name' => (string) ($s['name'] ?? $s['title'] ?? ('Склад #' . $id)),
                ];
            }
            $jsonExit(['success' => true, 'items' => $items]);

        // ── Неизвестный экшен ─────────────────────────────────────────────────
        default:
            throw new \InvalidArgumentException('Неизвестный action: ' . htmlspecialcharsbx($action));
    }

} catch (\Exception $e) {
    $jsonExit([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
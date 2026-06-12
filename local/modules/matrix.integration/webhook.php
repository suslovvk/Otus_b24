<?php

define('STOP_STATISTICS', true);
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Crm\ContactTable;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\Json;
use Matrix\Integration\Api\MatrixClient;
use Matrix\Integration\Sync\BonusSync;
use Matrix\Integration\Sync\ControlTurnoutSync;
use Matrix\Integration\Sync\PatientSync;
use Matrix\Integration\Sync\RecordSync;

header('Content-Type: application/json; charset=UTF-8');

const MODULE_ID = 'matrix.integration';

$exit = static function (int $code, array $payload): void {
    http_response_code($code);
    echo Json::encode($payload);
    exit;
};

if (!Loader::includeModule(MODULE_ID)) {
    $exit(503, ['error' => 'Module not loaded']);
}

// ── Просмотр лога (диагностика) ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['wlog'] ?? '') === 'mx_wh_log') {
    header('Content-Type: text/plain; charset=UTF-8');
    $lf = __DIR__ . '/webhook.log';
    echo file_exists($lf) ? file_get_contents($lf) : "(log is empty)\n";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $exit(405, ['error' => 'Method Not Allowed']);
}

$apiKey      = Option::get(MODULE_ID, 'MATRIX_API_KEY', '');
$incomingKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

if ($apiKey === '' || $incomingKey !== $apiKey) {
    $exit(401, ['error' => 'Unauthorized']);
}

$rawBody = file_get_contents('php://input');
if (empty($rawBody)) {
    $exit(400, ['error' => 'Empty body']);
}

try {
    $data = Json::decode($rawBody);
} catch (\Throwable $e) {
    $exit(400, ['error' => 'Invalid JSON']);
}

if (!is_array($data)) {
    $exit(400, ['error' => 'Expected JSON object']);
}

$_wlog = static function (string $step, array $ctx): void {
    $line = date('Y-m-d H:i:s') . " [{$step}] " . json_encode($ctx, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    file_put_contents(__DIR__ . '/webhook.log', $line, FILE_APPEND | LOCK_EX);
};

$type            = (string) ($data['type'] ?? '');
$payload         = isset($data['payload']) && is_array($data['payload']) ? $data['payload'] : [];
$matrixPatientId = (int) ($payload['patient_id'] ?? $payload['id'] ?? 0);

$_wlog('received', [
    'type'         => $type,
    'patient_id'   => $matrixPatientId,
    'payload_keys' => array_keys($payload),
]);

if (!$type) {
    $exit(400, ['error' => 'Missing type field']);
}

try {
    if (!Loader::includeModule('crm')) {
        $_wlog('error_crm', ['msg' => 'crm module failed to load']);
        $exit(503, ['error' => 'CRM module not loaded']);
    }

    $bitrixContactId = 0;
    if ($matrixPatientId > 0) {
        $contact = ContactTable::getRow([
            'filter' => ['=UF_MATRIX_PATIENT_ID' => $matrixPatientId],
            'select' => ['ID'],
        ]);
        if ($contact) {
            $bitrixContactId = (int) $contact['ID'];
        }
    }

    $_wlog('contact', ['matrix_patient_id' => $matrixPatientId, 'bitrix_contact_id' => $bitrixContactId]);

    $client = new MatrixClient();

    switch ($type) {

        case 'patient':
            if ($matrixPatientId <= 0) {
                $exit(422, ['error' => 'Missing patient_id']);
            }
            $sync   = new PatientSync($client);
            $result = $sync->syncById($matrixPatientId);
            $_wlog('patient_done', ['result' => $result, 'sync_log' => $sync->getLog()]);
            $exit(200, ['ok' => true, 'action' => 'patient_updated']);

        case 'record':
        case 'online_medcard_reception':
            if ($matrixPatientId <= 0 || $bitrixContactId <= 0) {
                $exit(422, ['error' => 'Patient not found in Bitrix']);
            }
            $sync = new RecordSync($client);
            if (!empty($payload) && isset($payload['datetime'])) {
                // полные данные записи в payload — используем напрямую
                if ($type === 'online_medcard_reception') {
                    $payload['_is_online'] = true;
                }
                $sync->upsertRecord($payload, $matrixPatientId, $bitrixContactId);
            } else {
                // минимальный payload (только id/canceled) — полная синхронизация через API
                $sync->syncForPatient($matrixPatientId, $bitrixContactId);
            }
            $exit(200, ['ok' => true, 'action' => 'record_synced']);

        case 'patient_bonus':
            if ($matrixPatientId <= 0 || $bitrixContactId <= 0) {
                $exit(422, ['error' => 'Patient not found in Bitrix']);
            }
            (new BonusSync($client))->syncForPatient($matrixPatientId, $bitrixContactId);
            $exit(200, ['ok' => true, 'action' => 'bonus_synced']);

        case 'patient_bonus_notice':
            (new BonusSync($client))->checkExpiringBonuses();
            $exit(200, ['ok' => true, 'action' => 'bonus_notice_processed']);

        case 'control_turnout_notice':
            if ($matrixPatientId > 0 && $bitrixContactId > 0 && !empty($payload)) {
                (new ControlTurnoutSync($client))->syncForPatient($matrixPatientId, $bitrixContactId);
            }
            (new ControlTurnoutSync($client))->processTodayTurnouts();
            $exit(200, ['ok' => true, 'action' => 'turnout_processed']);

        default:
            $exit(200, ['ok' => true, 'action' => 'ignored', 'type' => $type]);
    }

} catch (\Throwable $e) {
    $_wlog('error', [
        'type'  => $type ?? '',
        'error' => $e->getMessage(),
        'file'  => basename($e->getFile()),
        'line'  => $e->getLine(),
    ]);
    \Bitrix\Main\Diag\Debug::writeToFile(
        ['type' => $type, 'error' => $e->getMessage()],
        '[webhook]',
        '/bitrix/php_interface/log/matrix_integration.log'
    );
    $exit(500, ['error' => 'Internal error: ' . $e->getMessage()]);
}

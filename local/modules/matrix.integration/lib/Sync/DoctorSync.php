<?php

namespace Matrix\Integration\Sync;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;
use CUser;
use Matrix\Integration\Api\MatrixClient;
use Matrix\Integration\Orm\DoctorProcedureTable;
use Matrix\Integration\Util\ApiItem;

/**
 * Синхронизация врачей Matrix → пользователи Битрикс24.
 */
class DoctorSync
{
    private const MODULE_ID = 'matrix.integration';
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
                '[DoctorSync]',
                self::LOG_FILE
            );
        }
    }

    public function getLog(): array
    {
        return $this->log;
    }

    /**
     * Полная синхронизация всех врачей
     *
     * @return array{total:int, created:int, updated:int, skipped:int, errors:list<string>, warnings:list<string>}
     */
    public function syncAll(): array
    {
        $this->addLog('=== НАЧАЛО syncAll (синхронизация врачей) ===');
        
        $result = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'warnings' => [],
        ];

        if (!Loader::includeModule('main')) {
            $result['errors'][] = 'Модуль main не подключён';
            return $result;
        }

        $groupIds = $this->getDoctorGroupIds();
        if ($groupIds === []) {
            $result['warnings'][] = 'Не заданы группы для новых врачей (MATRIX_DOCTOR_GROUP_IDS) — создание новых пользователей отключено, только обновление существующих';
        }

        try {
            foreach ($this->client->paginate('doctors') as $doctor) {
                if (!is_array($doctor)) {
                    continue;
                }

                $result['total']++;
                $syncResult = $this->syncOneDoctor($doctor, $groupIds);

                if ($syncResult['action'] === 'created') {
                    $result['created']++;
                } elseif ($syncResult['action'] === 'updated') {
                    $result['updated']++;
                } elseif ($syncResult['action'] === 'skipped') {
                    $result['skipped']++;
                }

                if (!empty($syncResult['error'])) {
                    $result['errors'][] = $syncResult['error'];
                }

                $matrixDoctorId = ApiItem::int($doctor, 0, 'id', 'doctor_id');
                if ($matrixDoctorId > 0) {
                    $this->syncDoctorProcedures($matrixDoctorId, (array) ($doctor['procedures'] ?? []));
                }
            }
        } catch (\Throwable $e) {
            $result['errors'][] = $e->getMessage();
            $this->addLog('ОШИБКА: ' . $e->getMessage());
        }

        $this->addLog("=== КОНЕЦ syncAll: обработано {$result['total']} врачей, создано {$result['created']}, обновлено {$result['updated']}, пропущено {$result['skipped']} ===");
        
        return $result;
    }

    private function getDoctorGroupIds(): array
    {
        $raw = trim((string) Option::get(self::MODULE_ID, 'MATRIX_DOCTOR_GROUP_IDS', ''));
        if ($raw === '') {
            return [];
        }
        
        $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $ids = [];
        foreach ($parts as $p) {
            $i = (int) $p;
            if ($i > 0) {
                $ids[] = $i;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param list<int> $groupIds
     * @return array{action:string, user_id:int, matrix_id:int, error:string}
     */
    private function syncOneDoctor(array $doctorData, array $groupIds): array
    {
        $result = [
            'action' => 'none',
            'user_id' => 0,
            'matrix_id' => 0,
            'error' => '',
        ];

        $matrixDoctorId = ApiItem::int($doctorData, 0, 'id', 'matrix_id', 'doctor_id');
        if ($matrixDoctorId <= 0) {
            $result['error'] = 'Некорректный ID врача';
            $this->addLog('ОШИБКА: некорректный ID врача');
            return $result;
        }
        $result['matrix_id'] = $matrixDoctorId;

        $lastName = ApiItem::string($doctorData, '', 'last_name', 'surname', 'LAST_NAME');
        $firstName = ApiItem::string($doctorData, '', 'first_name', 'name', 'NAME');
        $secondName = ApiItem::string($doctorData, '', 'middle_name', 'patronymic', 'SECOND_NAME');
        $email = ApiItem::string($doctorData, '', 'email', 'mail', 'EMAIL');

        // Если нет раздельных полей, пытаемся разобрать full_name
        if ($lastName === '' && $firstName === '') {
            $fullName = ApiItem::string($doctorData, '', 'full_name', 'fio', 'title');
            if ($fullName !== '') {
                $parts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY);
                $lastName = $parts[0] ?? '';
                $firstName = $parts[1] ?? '';
                $secondName = $parts[2] ?? $secondName;
            }
        }

        $existingUser = UserTable::getList([
            'filter' => ['=UF_MATRIX_DOCTOR_ID' => $matrixDoctorId],
            'select' => ['ID', 'LOGIN', 'EMAIL', 'NAME', 'LAST_NAME'],
            'limit' => 1,
        ])->fetch();

        if ($existingUser) {
            $userId = (int) $existingUser['ID'];
            $updateResult = $this->updateUser($userId, $firstName, $lastName, $secondName, $email);
            
            if ($updateResult) {
                $result['action'] = 'updated';
                $result['user_id'] = $userId;
                $this->addLog("Обновлён врач: Matrix ID={$matrixDoctorId}, User ID={$userId}, ФИО={$lastName} {$firstName}");
            } else {
                $result['action'] = 'skipped';
                $result['error'] = "Ошибка обновления пользователя ID={$userId}";
                $this->addLog("ОШИБКА обновления врача: Matrix ID={$matrixDoctorId}");
            }
            return $result;
        }

        if ($lastName !== '' && $firstName !== '') {
            $userByFio = UserTable::getList([
                'filter' => [
                    '=LAST_NAME' => $lastName,
                    '=NAME' => $firstName,
                ],
                'select' => ['ID'],
                'limit' => 1,
            ])->fetch();
            
            if ($userByFio) {
                $userId = (int) $userByFio['ID'];
                $this->updateDoctorLink($userId, $matrixDoctorId);
                
                $result['action'] = 'updated';
                $result['user_id'] = $userId;
                $this->addLog("Обновлён врач (найден по ФИО): Matrix ID={$matrixDoctorId}, User ID={$userId}");
                return $result;
            }
        }

        if ($groupIds === []) {
            $result['action'] = 'skipped';
            $result['error'] = "Нет групп для создания врача Matrix ID={$matrixDoctorId}";
            $this->addLog("Пропущен врач (нет групп): Matrix ID={$matrixDoctorId}, ФИО={$lastName} {$firstName}");
            return $result;
        }

        $newUserId = $this->createUser($matrixDoctorId, $firstName, $lastName, $secondName, $email, $groupIds);
        
        if ($newUserId > 0) {
            $result['action'] = 'created';
            $result['user_id'] = $newUserId;
            $this->addLog("Создан врач: Matrix ID={$matrixDoctorId}, User ID={$newUserId}, ФИО={$lastName} {$firstName}");
        } else {
            $result['action'] = 'skipped';
            $result['error'] = "Ошибка создания врача Matrix ID={$matrixDoctorId}";
            $this->addLog("ОШИБКА создания врача: Matrix ID={$matrixDoctorId}");
        }

        return $result;
    }

    /** @return bool false если CUser::Update завершился ошибкой */
    private function updateUser(int $userId, string $firstName, string $lastName, string $secondName, string $email): bool
    {
        $fields = [];
        
        if ($firstName !== '') {
            $fields['NAME'] = $firstName;
        }
        if ($lastName !== '') {
            $fields['LAST_NAME'] = $lastName;
        }
        if ($secondName !== '') {
            $fields['SECOND_NAME'] = $secondName;
        }
        if ($email !== '') {
            $fields['EMAIL'] = $email;
        }
        
        if (empty($fields)) {
            return true;
        }
        
        $user = new CUser();
        $success = $user->Update($userId, $fields);
        
        if (!$success) {
            $this->addLog("updateUser: ОШИБКА ID={$userId}: " . ($user->LAST_ERROR ?: 'неизвестная'));
            return false;
        }
        
        return true;
    }

    /** Привязывает UF_MATRIX_DOCTOR_ID к существующему пользователю. */
    private function updateDoctorLink(int $userId, int $matrixDoctorId): void
    {
        $user = new CUser();
        $user->Update($userId, ['UF_MATRIX_DOCTOR_ID' => $matrixDoctorId]);
    }

    /**
     * @param list<int> $groupIds
     * @return int ID нового пользователя или 0 при ошибке
     */
    private function createUser(int $matrixDoctorId, string $firstName, string $lastName, string $secondName, string $email, array $groupIds): int
    {
        $login = 'matrix_doctor_' . $matrixDoctorId;

        $existsLogin = UserTable::getList([
            'filter' => ['=LOGIN' => $login],
            'select' => ['ID'],
            'limit' => 1,
        ])->fetch();
        
        if ($existsLogin) {
            $this->updateDoctorLink((int) $existsLogin['ID'], $matrixDoctorId);
            return (int) $existsLogin['ID'];
        }

        $password = bin2hex(random_bytes(10));
        
        if ($email === '') {
            $email = 'doctor.' . $matrixDoctorId . '@matrix.local';
        }
        
        if ($firstName === '') {
            $firstName = 'Врач';
        }
        if ($lastName === '') {
            $lastName = 'Matrix#' . $matrixDoctorId;
        }

        $fields = [
            'LOGIN' => $login,
            'PASSWORD' => $password,
            'CONFIRM_PASSWORD' => $password,
            'EMAIL' => $email,
            'NAME' => $firstName,
            'LAST_NAME' => $lastName,
            'SECOND_NAME' => $secondName,
            'ACTIVE' => 'Y',
            'GROUP_ID' => $groupIds,
            'UF_MATRIX_DOCTOR_ID' => $matrixDoctorId,
        ];

        $user = new CUser();
        $userId = (int) $user->Add($fields);
        
        if ($userId <= 0) {
            $this->addLog("createUser: ОШИБКА Matrix ID={$matrixDoctorId}: " . ($user->LAST_ERROR ?: 'неизвестная'));
            return 0;
        }
        
        return $userId;
    }

    /**
     * Синхронизация одного врача по Matrix ID — для обработки вебхуков.
     *
     * @return array{action:string, user_id:int, matrix_id:int, error:string}
     */
    public function syncById(int $matrixDoctorId): array
    {
        $this->addLog("syncById: запрос врача Matrix ID={$matrixDoctorId}");
        
        try {
            $response = $this->client->get('doctors/' . $matrixDoctorId);
            
            if (!$response || empty($response)) {
                $this->addLog("syncById: врач с ID={$matrixDoctorId} не найден");
                return [
                    'action' => 'none',
                    'user_id' => 0,
                    'matrix_id' => $matrixDoctorId,
                    'error' => 'Врач не найден в Matrix',
                ];
            }
            
            $groupIds = $this->getDoctorGroupIds();
            return $this->syncOneDoctor($response, $groupIds);
            
        } catch (\Throwable $e) {
            $this->addLog("syncById: ошибка: " . $e->getMessage());
            return [
                'action' => 'none',
                'user_id' => 0,
                'matrix_id' => $matrixDoctorId,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Перезаписывает связи врач → процедура → специальность.
     *
     * @param array $procedures Массив процедур из ответа /doctors API
     */
    private function syncDoctorProcedures(int $matrixDoctorId, array $procedures): void
    {
        \Bitrix\Main\Application::getConnection()->query(
            'DELETE FROM `' . DoctorProcedureTable::getTableName() . '`'
            . ' WHERE MATRIX_DOCTOR_ID = ' . $matrixDoctorId
        );

        if (empty($procedures)) {
            return;
        }

        $seen = [];
        foreach ($procedures as $proc) {
            $procId      = (int) ($proc['id'] ?? 0);
            $specialties = (array) ($proc['specialties'] ?? []);
            if ($procId <= 0) {
                continue;
            }
            foreach ($specialties as $spec) {
                $specId   = (int) ($spec['id'] ?? 0);
                $specName = (string) ($spec['name'] ?? '');
                if ($specId <= 0) {
                    continue;
                }
                $key = $procId . '-' . $specId;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                DoctorProcedureTable::add([
                    'MATRIX_DOCTOR_ID'    => $matrixDoctorId,
                    'MATRIX_PROCEDURE_ID' => $procId,
                    'SPECIALTY_ID'        => $specId,
                    'SPECIALTY_NAME'      => $specName,
                ]);
            }
        }

        $this->addLog("syncDoctorProcedures: doctor={$matrixDoctorId}, procedures=" . count($procedures));
    }
}
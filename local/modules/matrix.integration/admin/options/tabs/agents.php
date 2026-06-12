<?php
$agentClass = '\Matrix\Integration\Agent\AgentManager::';

$agentDefs = [
    ['method' => 'syncCatalogAgent',         'label' => 'Синхронизация каталога',       'time' => '02:00'],
    ['method' => 'checkZeroStockAgent',       'label' => 'Проверка нулевых остатков',    'time' => '02:15'],
    ['method' => 'syncDoctorsAgent',          'label' => 'Синхронизация врачей',         'time' => '02:30'],
    ['method' => 'syncPatientsAgent',         'label' => 'Синхронизация пациентов',      'time' => '03:00'],
    ['method' => 'processTurnoutsAgent',      'label' => 'Контрольные явки',             'time' => '08:00'],
    ['method' => 'checkExpiringBonusesAgent', 'label' => 'Сгорающие бонусы',            'time' => '09:00'],
];

// Кнопки ручного запуска (действия, доступные через ajax.php)
$manualActions = [
    'checkZeroStockAgent'       => ['action' => 'check_zero_stock',       'btn' => 'matrix-run-zero-stock'],
    'processTurnoutsAgent'      => ['action' => 'process_turnouts',       'btn' => 'matrix-run-turnouts'],
    'checkExpiringBonusesAgent' => ['action' => 'check_expiring_bonuses', 'btn' => 'matrix-run-expiring'],
];

// Получаем зарегистрированные агенты из БД
$registeredAgents = [];
$res = \CAgent::GetList([], ['MODULE' => 'matrix.integration']);
while ($row = $res->Fetch()) {
    $registeredAgents[$row['NAME']] = $row;
}
?>

<tr class="heading">
    <td colspan="2">Агенты фоновой синхронизации</td>
</tr>

<tr>
    <td colspan="2">
        <small>
            Агенты запускаются автоматически по расписанию через механизм агентов Битрикс24.
            Убедитесь, что на сервере настроен cron-запуск агентов
            (<code>/bitrix/php_interface/cron_events.php</code>).
        </small>
    </td>
</tr>

<tr>
    <td colspan="2" style="padding:8px 0 4px">
        <table class="adm-list-table" style="width:100%;border-collapse:collapse">
            <thead>
                <tr class="adm-list-table-header">
                    <th style="padding:4px 8px;text-align:left">Агент</th>
                    <th style="padding:4px 8px;text-align:left">Расписание</th>
                    <th style="padding:4px 8px;text-align:left">Статус</th>
                    <th style="padding:4px 8px;text-align:left">Следующий запуск</th>
                    <th style="padding:4px 8px;text-align:left">Действие</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($agentDefs as $agent):
                $call      = $agentClass . $agent['method'] . '();';
                $regRow    = $registeredAgents[$call] ?? null;
                $isReg     = $regRow !== null;
                $nextTime  = $isReg ? ($regRow['NEXT_EXEC'] ?? '—') : '—';
                $isActive  = $isReg && ($regRow['ACTIVE'] ?? 'N') === 'Y';
                $manual    = $manualActions[$agent['method']] ?? null;
            ?>
                <tr class="adm-list-table-row">
                    <td style="padding:4px 8px"><?= htmlspecialcharsbx($agent['label']) ?></td>
                    <td style="padding:4px 8px">Ежедневно, <?= $agent['time'] ?></td>
                    <td style="padding:4px 8px">
                        <?php if ($isReg && $isActive): ?>
                            <span style="color:#3c763d;font-weight:600">✓ Активен</span>
                        <?php elseif ($isReg): ?>
                            <span style="color:#a94442;font-weight:600">Неактивен</span>
                        <?php else: ?>
                            <span style="color:#a94442;font-weight:600">✗ Не зарегистрирован</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:4px 8px"><?= htmlspecialcharsbx($nextTime) ?></td>
                    <td style="padding:4px 8px">
                        <?php if ($manual): ?>
                            <button type="button" class="adm-btn adm-btn-save"
                                    id="<?= $manual['btn'] ?>"
                                    data-action="<?= $manual['action'] ?>">
                                Запустить
                            </button>
                            <span id="<?= $manual['btn'] ?>-result" style="margin-left:6px;font-size:12px"></span>
                        <?php else: ?>
                            <span style="color:#777;font-size:12px">см. вкладку</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </td>
</tr>

<tr>
    <td colspan="2" style="padding-top:12px">
        <button type="button" class="adm-btn" id="matrix-register-agents">
            Перерегистрировать агентов
        </button>
        <span id="matrix-register-agents-result" style="margin-left:10px"></span>
        <br><small style="color:#777">
            Используйте если агенты не зарегистрированы или были удалены случайно.
        </small>
    </td>
</tr>

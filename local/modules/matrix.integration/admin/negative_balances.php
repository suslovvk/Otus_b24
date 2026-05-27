<?php

/**
 * Страница: контакты с отрицательным балансом (ТЗ раздел 6.5).
 * URL: /local/modules/matrix.integration/admin/negative_balances.php
 */

define('STOP_STATISTICS', true);
define('NO_KEEP_STATISTIC', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Crm\ContactTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Matrix\Integration\Orm\BalanceTable;

Loc::loadMessages(__FILE__);

global $APPLICATION, $USER;

if (
    !$USER->IsAdmin()
    || !Loader::includeModule('matrix.integration')
    || !Loader::includeModule('crm')
) {
    $APPLICATION->AuthForm('Access denied');
    die();
}

$APPLICATION->SetTitle(Loc::getMessage('MATRIX_NEGATIVE_BALANCES_PAGE_TITLE'));

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$rows = BalanceTable::getList([
    'filter' => ['<BALANCE' => 0],
    'select' => ['ID', 'MATRIX_PATIENT_ID', 'BITRIX_CONTACT_ID', 'BALANCE', 'DATE_UPDATE'],
    'order'  => ['BALANCE' => 'ASC'],
])->fetchAll();

$contactIds = array_filter(array_unique(array_column($rows, 'BITRIX_CONTACT_ID')));
$contactMap = [];
if ($contactIds) {
    foreach (ContactTable::getList([
        'filter' => ['@ID' => $contactIds],
        'select' => ['ID', 'NAME', 'LAST_NAME', 'SECOND_NAME'],
    ])->fetchAll() as $c) {
        $contactMap[(int) $c['ID']] = trim($c['LAST_NAME'] . ' ' . $c['NAME'] . ' ' . $c['SECOND_NAME']);
    }
}
?>
<style>
.mx-neg-table { width:100%; border-collapse:collapse; font-size:13px; }
.mx-neg-table th { padding:8px 12px; background:#f0f2f5; color:#555; text-align:left; border-bottom:2px solid #ddd; }
.mx-neg-table td { padding:8px 12px; border-bottom:1px solid #f0f0f0; }
.mx-neg-table tr:hover td { background:#fafbfc; }
.mx-balance-neg { color:#c0392b; font-weight:600; }
.mx-empty { padding:24px; color:#aaa; text-align:center; font-size:14px; }
.mx-page-header { display:flex; align-items:center; gap:16px; margin-bottom:18px; flex-wrap:wrap; }
.mx-page-header h2 { margin:0; font-size:18px; }
</style>

<div class="mx-page-header">
    <h2><?= Loc::getMessage('MATRIX_NEGATIVE_BALANCES_HEADER') ?></h2>
    <span style="color:#888;font-size:12px"><?= Loc::getMessage('MATRIX_NEGATIVE_BALANCES_TOTAL_LABEL') ?><strong><?= count($rows) ?></strong></span>
</div>

<?php if (empty($rows)): ?>
    <div class="mx-empty"><?= Loc::getMessage('MATRIX_NEGATIVE_BALANCES_NO_DATA') ?></div>
<?php else: ?>
    <table class="mx-neg-table">
        <thead>
        <tr>
            <th><?= Loc::getMessage('MATRIX_NEGATIVE_BALANCES_COL_NUM') ?></th>
            <th><?= Loc::getMessage('MATRIX_NEGATIVE_BALANCES_COL_PATIENT') ?></th>
            <th><?= Loc::getMessage('MATRIX_NEGATIVE_BALANCES_COL_PATIENT_ID') ?></th>
            <th><?= Loc::getMessage('MATRIX_NEGATIVE_BALANCES_COL_BALANCE') ?></th>
            <th><?= Loc::getMessage('MATRIX_NEGATIVE_BALANCES_COL_UPDATED') ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $i => $row):
            $contactId  = (int) $row['BITRIX_CONTACT_ID'];
            $name       = $contactMap[$contactId] ?? (Loc::getMessage('MATRIX_NEGATIVE_BALANCES_CONTACT_FALLBACK') . $contactId);
            $contactUrl = $contactId > 0 ? SITE_DIR . 'crm/contact/show/' . $contactId . '/' : '';
        ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td>
                <?php if ($contactUrl): ?>
                    <a href="<?= htmlspecialchars($contactUrl) ?>" target="_blank">
                        <?= htmlspecialchars($name) ?>
                    </a>
                <?php else: ?>
                    <?= htmlspecialchars($name) ?>
                <?php endif; ?>
            </td>
            <td><?= (int) $row['MATRIX_PATIENT_ID'] ?></td>
            <td class="mx-balance-neg"><?= number_format((float) $row['BALANCE'], 2, '.', ' ') ?> ₽</td>
            <td><?= htmlspecialchars((string) $row['DATE_UPDATE']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

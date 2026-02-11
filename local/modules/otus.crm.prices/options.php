<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * @global CMain $APPLICATION
 */

$module_id = 'otus.crm.prices';

$aTabs = [
    [
        'DIV' => 'settings',
        'TAB' => Loc::getMessage('OTUS_PRICES_OPTIONS_TAB_SETTINGS'),
        'TITLE' => Loc::getMessage('OTUS_PRICES_OPTIONS_TAB_SETTINGS_TITLE'),
    ],
];

$tabControl = new CAdminTabControl('tabControl', $aTabs);
$tabControl->Begin();
?>

<form method="post" action="<?php echo $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($module_id) ?>&lang=<?= LANGUAGE_ID ?>">
    <?php
    $tabControl->BeginNextTab();
    ?>
    <tr>
        <td colspan="2">
            <?= Loc::getMessage('OTUS_PRICES_OPTIONS_NO_SETTINGS') ?>
        </td>
    </tr>
    <?php
    $tabControl->Buttons();
    ?>
    <?= bitrix_sessid_post() ?>
</form>

<?php $tabControl->End(); ?>
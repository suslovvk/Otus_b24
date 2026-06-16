<?php

use Bitrix\Main\Localization\Loc;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

Loc::loadMessages(__FILE__);

$APPLICATION->SetTitle(Loc::getMessage('DZ11_INDEX_TITLE'));

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_after.php';
?>
<div style="max-width:680px; margin:40px auto; font-family:sans-serif; line-height:1.6;">
    <h2><?= Loc::getMessage('DZ11_INDEX_TITLE') ?></h2>

    <p><?= Loc::getMessage('DZ11_INDEX_DESCRIPTION') ?></p>

    <hr>

    <h3>Шаг 1. Создать пользовательское поле</h3>
    <p><?= Loc::getMessage('DZ11_INDEX_FIELD_HINT') ?></p>
    <a href="/local/apps/dz11/install_field.php"
       style="display:inline-block; padding:8px 16px; background:#4caf50; color:#fff; border-radius:4px; text-decoration:none;">
        <?= Loc::getMessage('DZ11_INDEX_INSTALL_BTN') ?>
    </a>

    <hr>

    <h3>Шаг 2. Подключить обработчик событий</h3>
    <p><?= Loc::getMessage('DZ11_INDEX_EVENT_HINT') ?></p>
    <ul>
        <li><?= Loc::getMessage('DZ11_INDEX_EVENT_NAME') ?></li>
        <li><?= Loc::getMessage('DZ11_INDEX_HANDLER_URL') ?></li>
    </ul>
    <p><?= Loc::getMessage('DZ11_INDEX_SETUP_HINT') ?></p>
    <a href="/local/apps/dz11/setup_event.php"
       style="display:inline-block; padding:8px 16px; background:#2196f3; color:#fff; border-radius:4px; text-decoration:none;">
        <?= Loc::getMessage('DZ11_INDEX_SETUP_BTN') ?>
    </a>
</div>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';

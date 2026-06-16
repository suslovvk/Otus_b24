<?php

namespace Otus\Dz8;

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use CMain;
use CUtil;

Loc::loadMessages(__DIR__ . '/lang/ru/StartWorkDayAssets.php');

/**
 * Подключает скрипт модального окна подтверждения начала рабочего дня.
 */
class StartWorkDayAssets
{
    private const JS_PATH = '/local/js/dz8/start-work-day.js';

    private const TIMEMAN_PANEL_SOURCE_FILE = '/bitrix/modules/timeman/install/js/timeman/work-status-control-panel/config.php';

    private const TIMEMAN_BUTTON_LABEL_MESSAGE = 'TIMEMAN_WORK_STATUS_CONTROL_PANEL_ACTION_START';

    /**
     * @return void
     */
    public static function connect(): void
    {
        /** @var CMain $APPLICATION */
        global $APPLICATION;

        Loc::loadMessages(Application::getDocumentRoot() . self::TIMEMAN_PANEL_SOURCE_FILE);

        $texts = [
            'buttonLabel' => Loc::getMessage(self::TIMEMAN_BUTTON_LABEL_MESSAGE),
            'title'       => Loc::getMessage('DZ8_POPUP_TITLE'),
            'text'        => Loc::getMessage('DZ8_POPUP_TEXT'),
            'confirmText' => Loc::getMessage('DZ8_POPUP_CONFIRM_BUTTON'),
        ];

        $APPLICATION->AddHeadString(
            '<script>window.DZ8_START_WORK_DAY_TEXTS = ' . CUtil::PhpToJSObject($texts) . ';</script>',
            true
        );
        $APPLICATION->AddHeadScript(self::JS_PATH);
    }
}

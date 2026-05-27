<?php

namespace Matrix\Integration\CrmTab;

use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Регистрирует 5 вкладок в карточке Контакта CRM через событие onEntityDetailsTabsInitialized.
 * Каждая вкладка ссылается на собственный Bitrix-компонент с lazy-загрузкой.
 */
class ContactTab
{
    private const MODULE_ID          = 'matrix.integration';
    private const COMPONENT_NS       = 'matrix';
    private const CONTACT_TYPE_ID    = 3; // CCrmOwnerType::Contact

    private static function getTabs(): array
    {
        return [
            ['id' => 'balance',  'name' => Loc::getMessage('MATRIX_TAB_BALANCE_BONUSES'), 'component' => 'crm.patient.balance'],
            ['id' => 'records',  'name' => Loc::getMessage('MATRIX_TAB_RECORDS'),          'component' => 'crm.patient.records'],
            ['id' => 'turnouts', 'name' => Loc::getMessage('MATRIX_TAB_TURNOUTS'),          'component' => 'crm.patient.turnouts'],
            ['id' => 'receipts', 'name' => Loc::getMessage('MATRIX_TAB_RECEIPTS'),          'component' => 'crm.patient.receipts'],
        ];
    }

    /**
     * Обработчик события onEntityDetailsTabsInitialized.
     * Добавляет вкладки только для сущности «Контакт».
     */
    public static function onTabsInitialized(Event $event): EventResult
    {
        $entityTypeId = (int) $event->getParameter('entityTypeID');
        $entityId     = (int) $event->getParameter('entityID');
        $tabs         = (array) $event->getParameter('tabs');

        if ($entityTypeId !== self::CONTACT_TYPE_ID || $entityId <= 0) {
            return new EventResult(EventResult::SUCCESS, ['tabs' => $tabs]);
        }

        if (!Loader::includeModule(self::MODULE_ID)) {
            return new EventResult(EventResult::SUCCESS, ['tabs' => $tabs]);
        }

        $siteParam = 'site=' . SITE_ID . '&' . bitrix_sessid_get();
        $params    = ['ENTITY_ID' => $entityId, 'ENTITY_TYPE_ID' => $entityTypeId];

        foreach (self::getTabs() as $def) {
            $tabs[] = [
                'id'      => 'mx_' . $def['id'] . '_' . $entityTypeId . '_' . $entityId,
                'name'    => $def['name'],
                'enabled' => true,
                'loader'  => [
                    'serviceUrl'   => sprintf(
                        '/local/components/%s/%s/lazyload.ajax.php?%s',
                        self::COMPONENT_NS,
                        $def['component'],
                        $siteParam
                    ),
                    'componentData' => [
                        'template' => '',
                        'params'   => $params,
                    ],
                ],
            ];
        }

        return new EventResult(EventResult::SUCCESS, ['tabs' => $tabs]);
    }
}

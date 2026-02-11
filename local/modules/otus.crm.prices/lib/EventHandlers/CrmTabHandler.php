<?php
namespace Otus\Crm\Prices\EventHandlers;

use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Обработчик событий для добавления вкладки в CRM
 *
 * Class CrmTabHandler
 * @package Otus\Crm\Prices\EventHandlers
 */
class CrmTabHandler
{
    /**
     * Обработчик события добавления вкладки в карточку CRM
     *
     * @param Event $event
     * @return EventResult
     */
    public static function onEntityDetailsTabsInitialized(Event $event): EventResult
    {
        $entityTypeId = $event->getParameter('entityTypeID');
        $entityId = $event->getParameter('entityID');
        $tabs = $event->getParameter('tabs');

        $tabs[] = [
            'id' => 'prices_tab_' . $entityTypeId . '_' . $entityId,
            'name' => Loc::getMessage('OTUS_PRICES_TAB_TITLE'),
            'enabled' => true,
            'loader' => [
                'serviceUrl' => sprintf(
                    '/local/components/otus/crm.prices.list/lazyload.ajax.php?site=%s&%s',
                    SITE_ID,
                    bitrix_sessid_get()
                ),
                'componentData' => [
                    'template' => '',
                    'params' => [
                        'ENTITY_ID' => $entityId,
                        'ENTITY_TYPE_ID' => $entityTypeId,
                    ],
                ],
            ],
        ];

        return new EventResult(EventResult::SUCCESS, ['tabs' => $tabs]);
    }
}
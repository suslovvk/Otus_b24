<?php

namespace Local\Events;

use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Loader;
use CIBlockElement;
use CCrmDeal;

/**
 * Двусторонняя синхронизация инфоблока «Заявки» и сделок CRM.
 */
class IblockCrmSync
{
    private const IBLOCK_CODE = 'requests';
    private const LOG_FILE    = '/local/logs/iblock_crm_sync.log';

    private const PROP_DEAL        = 'DEAL_ID';
    private const PROP_AMOUNT      = 'AMOUNT';
    private const PROP_RESPONSIBLE = 'RESPONSIBLE_ID';
    private const PROP_CLIENT      = 'CLIENT_ID';

    // Значения свойств CRM_ELEMENT хранятся в формате "{prefix}{id}"
    private const CRM_DEAL_PREFIX    = 'D_';
    private const CRM_CONTACT_PREFIX = 'C_';

    private static bool $syncingIblockToDeal = false;
    private static bool $syncingDealToIblock = false;

    private static ?int $iblockId = null;

    /**
     * Обработчик OnAfterIBlockElementAdd.
     * Если DEAL_ID не заполнен — создаёт сделку и записывает её ID обратно в элемент.
     * Если заполнен — обновляет существующую сделку.
     *
     * @param array $fields
     *
     * @return void
     */
    public static function onAfterIBlockElementAdd(array &$fields): void
    {
        self::log('onAfterIBlockElementAdd called', [
            'syncingDealToIblock' => self::$syncingDealToIblock,
            'elementId'           => $fields['ID'] ?? null,
            'iblockId'            => $fields['IBLOCK_ID'] ?? null,
            'expectedIblockId'    => self::getIblockId(),
        ]);

        if (self::$syncingDealToIblock) {
            return;
        }

        $elementId = (int) ($fields['ID'] ?? 0);
        $iblockId  = (int) ($fields['IBLOCK_ID'] ?? 0);

        if ($elementId <= 0 || $iblockId !== self::getIblockId()) {
            self::log('onAfterIBlockElementAdd: iblock mismatch or no elementId, skip');
            return;
        }

        if (!Loader::includeModule('crm') || !Loader::includeModule('iblock')) {
            self::log('onAfterIBlockElementAdd: module not loaded');
            return;
        }

        $props     = self::readElementProps($elementId);
        $dealId    = self::parseCrmId($props[self::PROP_DEAL]['VALUE'] ?? '', self::CRM_DEAL_PREFIX);
        $contactId = self::parseCrmId($props[self::PROP_CLIENT]['VALUE'] ?? '', self::CRM_CONTACT_PREFIX);

        self::log('onAfterIBlockElementAdd: props read', [
            'dealId'        => $dealId,
            'amount'        => $props[self::PROP_AMOUNT]['VALUE'] ?? null,
            'responsibleId' => $props[self::PROP_RESPONSIBLE]['VALUE'] ?? null,
            'contactId'     => $contactId,
        ]);

        if ($dealId <= 0) {
            $dealId = self::createDeal(
                'Заявка #' . $elementId,
                (float) ($props[self::PROP_AMOUNT]['VALUE'] ?? 0),
                (int)   ($props[self::PROP_RESPONSIBLE]['VALUE'] ?? 0),
                $contactId
            );

            self::log('onAfterIBlockElementAdd: deal created', ['newDealId' => $dealId]);

            if ($dealId > 0) {
                self::$syncingIblockToDeal = true;
                CIBlockElement::SetPropertyValuesEx(
                    $elementId,
                    self::getIblockId(),
                    [self::PROP_DEAL => self::CRM_DEAL_PREFIX . $dealId]
                );
                self::$syncingIblockToDeal = false;
            }
        } else {
            self::updateDeal(
                $dealId,
                (float) ($props[self::PROP_AMOUNT]['VALUE'] ?? 0),
                (int)   ($props[self::PROP_RESPONSIBLE]['VALUE'] ?? 0),
                $contactId
            );
        }
    }

    /**
     * Обработчик OnAfterIBlockElementUpdate.
     * Синхронизирует изменённые поля в связанную сделку.
     *
     * @param array $fields
     *
     * @return void
     */
    public static function onAfterIBlockElementUpdate(array &$fields): void
    {
        if (self::$syncingDealToIblock) {
            return;
        }

        $elementId = (int) ($fields['ID'] ?? 0);
        $iblockId  = (int) ($fields['IBLOCK_ID'] ?? 0);

        if ($elementId <= 0 || $iblockId !== self::getIblockId()) {
            return;
        }

        if (!Loader::includeModule('crm') || !Loader::includeModule('iblock')) {
            return;
        }

        $props     = self::readElementProps($elementId);
        $dealId    = self::parseCrmId($props[self::PROP_DEAL]['VALUE'] ?? '', self::CRM_DEAL_PREFIX);
        $contactId = self::parseCrmId($props[self::PROP_CLIENT]['VALUE'] ?? '', self::CRM_CONTACT_PREFIX);

        if ($dealId <= 0) {
            return;
        }

        self::updateDeal(
            $dealId,
            (float) ($props[self::PROP_AMOUNT]['VALUE'] ?? 0),
            (int)   ($props[self::PROP_RESPONSIBLE]['VALUE'] ?? 0),
            $contactId
        );
    }

    /**
     * Обработчик OnAfterCrmDealAdd.
     * Создаёт элемент инфоблока «Заявки» для новой сделки.
     *
     * @param array $fields
     *
     * @return void
     */
    public static function onDealAfterAdd(array &$fields): void
    {
        if (self::$syncingIblockToDeal) {
            return;
        }

        $dealId = (int) ($fields['ID'] ?? 0);

        if ($dealId <= 0 || !Loader::includeModule('iblock')) {
            return;
        }

        $amount        = (float) ($fields['OPPORTUNITY']    ?? 0);
        $responsibleId = (int)   ($fields['ASSIGNED_BY_ID'] ?? 0);
        $contactId     = (int)   ($fields['CONTACT_ID']     ?? 0);

        self::log('onDealAfterAdd', ['dealId' => $dealId, 'amount' => $amount, 'contactId' => $contactId]);

        self::$syncingDealToIblock = true;

        $el       = new CIBlockElement();
        $elFields = [
            'IBLOCK_ID'       => self::getIblockId(),
            'NAME'            => 'Заявка по сделке #' . $dealId,
            'ACTIVE'          => 'Y',
            'PROPERTY_VALUES' => [
                self::PROP_DEAL        => self::CRM_DEAL_PREFIX . $dealId,
                self::PROP_AMOUNT      => $amount,
                self::PROP_RESPONSIBLE => $responsibleId ?: false,
                self::PROP_CLIENT      => $contactId > 0 ? self::CRM_CONTACT_PREFIX . $contactId : false,
            ],
        ];
        $el->Add($elFields);

        self::$syncingDealToIblock = false;
    }

    /**
     * Обработчик OnAfterCrmDealUpdate.
     * Обновляет связанный элемент инфоблока при изменении сделки.
     *
     * @param array $fields
     *
     * @return void
     */
    public static function onDealAfterUpdate(array &$fields): void
    {
        if (self::$syncingIblockToDeal) {
            return;
        }

        $dealId = (int) ($fields['ID'] ?? 0);

        if ($dealId <= 0 || !Loader::includeModule('iblock')) {
            return;
        }

        $elementId = self::findElementByDealId($dealId);
        if ($elementId <= 0) {
            return;
        }

        $contactId = (int) ($fields['CONTACT_ID'] ?? 0);

        self::log('onDealAfterUpdate', ['dealId' => $dealId, 'elementId' => $elementId, 'contactId' => $contactId]);

        self::$syncingDealToIblock = true;

        $propsToUpdate = [
            self::PROP_AMOUNT      => (float) ($fields['OPPORTUNITY']    ?? 0),
            self::PROP_RESPONSIBLE => (int)   ($fields['ASSIGNED_BY_ID'] ?? 0),
            self::PROP_CLIENT      => $contactId > 0 ? self::CRM_CONTACT_PREFIX . $contactId : false,
        ];

        CIBlockElement::SetPropertyValuesEx($elementId, self::getIblockId(), $propsToUpdate);

        self::$syncingDealToIblock = false;
    }

    /**
     * Создаёт новую сделку и возвращает её ID.
     *
     * @param string $title
     * @param float  $amount
     * @param int    $responsibleId
     * @param int    $contactId
     *
     * @return int
     */
    private static function createDeal(string $title, float $amount, int $responsibleId, int $contactId = 0): int
    {
        self::$syncingIblockToDeal = true;

        $dealFields = [
            'TITLE'          => $title,
            'OPPORTUNITY'    => $amount,
            'CURRENCY_ID'    => 'RUB',
            'ASSIGNED_BY_ID' => $responsibleId > 0 ? $responsibleId : 1,
            'OPENED'         => 'Y',
        ];

        if ($contactId > 0) {
            $dealFields['CONTACT_ID'] = $contactId;
        }

        $deal   = new CCrmDeal(false);
        $dealId = (int) $deal->Add($dealFields);

        self::$syncingIblockToDeal = false;

        if ($dealId <= 0) {
            self::log('createDeal FAILED', ['error' => $deal->LAST_ERROR ?? 'unknown']);
        }

        return $dealId;
    }

    /**
     * Обновляет сумму, ответственного и клиента в существующей сделке.
     *
     * @param int   $dealId
     * @param float $amount
     * @param int   $responsibleId
     * @param int   $contactId
     *
     * @return void
     */
    private static function updateDeal(int $dealId, float $amount, int $responsibleId, int $contactId = 0): void
    {
        self::$syncingIblockToDeal = true;

        $updateFields = ['OPPORTUNITY' => $amount];

        if ($responsibleId > 0) {
            $updateFields['ASSIGNED_BY_ID'] = $responsibleId;
        }

        if ($contactId > 0) {
            $updateFields['CONTACT_ID'] = $contactId;
        }

        $deal = new CCrmDeal(false);
        $deal->Update($dealId, $updateFields);

        self::$syncingIblockToDeal = false;
    }

    /**
     * Читает свойства элемента инфоблока через GetPropertyValuesArray.
     *
     * @param int $elementId
     *
     * @return array
     */
    private static function readElementProps(int $elementId): array
    {
        $map = [];
        CIBlockElement::GetPropertyValuesArray(
            $map,
            self::getIblockId(),
            ['ID' => $elementId]
        );

        return $map[$elementId] ?? [];
    }

    /**
     * Разбирает значение свойства CRM_ELEMENT по известному префиксу ("D_42" -> 42, "C_5" -> 5).
     *
     * @param string $raw
     * @param string $prefix
     *
     * @return int
     */
    private static function parseCrmId(string $raw, string $prefix): int
    {
        $raw = trim($raw);

        if ($raw === '' || $raw === '0') {
            return 0;
        }

        if (str_starts_with($raw, $prefix)) {
            return (int) substr($raw, strlen($prefix));
        }

        return (int) $raw;
    }

    /**
     * Находит ID элемента инфоблока по ID связанной сделки.
     * Проверяет оба формата значения: "D_42" и "42".
     *
     * @param int $dealId
     *
     * @return int
     */
    private static function findElementByDealId(int $dealId): int
    {
        foreach ([self::CRM_DEAL_PREFIX . $dealId, (string) $dealId] as $searchVal) {
            $result = CIBlockElement::GetList(
                [],
                [
                    'IBLOCK_ID'                   => self::getIblockId(),
                    'PROPERTY_' . self::PROP_DEAL  => $searchVal,
                    'ACTIVE'                       => 'Y',
                ],
                false,
                ['nTopCount' => 1],
                ['ID']
            );

            if ($row = $result->Fetch()) {
                return (int) $row['ID'];
            }
        }

        return 0;
    }

    /**
     * Записывает строку в лог-файл.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    private static function log(string $message, array $context = []): void
    {
        Debug::writeToFile(
            array_merge(['msg' => $message], $context),
            '[IblockCrmSync ' . date('H:i:s') . ']',
            self::LOG_FILE
        );
    }

    /**
     * Возвращает ID инфоблока по символьному коду (кешируется в статическом поле).
     *
     * @return int
     */
    private static function getIblockId(): int
    {
        if (self::$iblockId !== null) {
            return self::$iblockId;
        }

        if (!Loader::includeModule('iblock')) {
            return 0;
        }

        $result = \CIBlock::GetList(
            [],
            ['CODE' => self::IBLOCK_CODE, 'CHECK_PERMISSIONS' => 'N']
        );

        self::$iblockId = ($row = $result->Fetch()) ? (int) $row['ID'] : 0;

        return self::$iblockId;
    }
}

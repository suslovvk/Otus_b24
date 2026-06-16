<?php

namespace Otus\Callback\Requests\Rest;

use Bitrix\Main\Localization\Loc;
use Bitrix\Rest\RestException;
use CRestServer;
use Otus\Callback\Requests\Logger;
use Otus\Callback\Requests\Orm\CallbackRequestTable;

Loc::loadMessages(__FILE__);

/**
 * CRUD REST-методы для заявок на обратный звонок.
 */
class CallbackRequestService
{
    private const PAGE_SIZE = 50;

    /**
     * @param array $params
     * @param mixed $n
     * @param CRestServer $server
     *
     * @return array
     * @throws RestException
     */
    public static function add(array $params, $n, CRestServer $server): array
    {
        $fields = array_change_key_case($params, CASE_UPPER);

        if (empty($fields['NAME'])) {
            throw new RestException(Loc::getMessage('OTUS_CALLBACK_REQUESTS_ERR_EMPTY_NAME'), 'EMPTY_NAME');
        }

        if (empty($fields['PHONE'])) {
            throw new RestException(Loc::getMessage('OTUS_CALLBACK_REQUESTS_ERR_EMPTY_PHONE'), 'EMPTY_PHONE');
        }

        if (isset($fields['STATUS'])) {
            self::assertStatusIsAllowed($fields['STATUS']);
        }

        $addResult = CallbackRequestTable::add([
            'NAME' => $fields['NAME'],
            'PHONE' => $fields['PHONE'],
            'COMMENT' => $fields['COMMENT'] ?? null,
            'STATUS' => $fields['STATUS'] ?? CallbackRequestTable::STATUS_NEW,
        ]);

        if (!$addResult->isSuccess()) {
            throw new RestException(implode('; ', $addResult->getErrorMessages()), 'ADD_ERROR');
        }

        $result = ['ID' => $addResult->getId()];

        Logger::log('otus.callback.request.add', $params, $result);

        return $result;
    }

    /**
     * @param array $params
     * @param mixed $n
     * @param CRestServer $server
     *
     * @return array
     * @throws RestException
     */
    public static function get(array $params, $n, CRestServer $server): array
    {
        $fields = array_change_key_case($params, CASE_UPPER);
        $id = self::assertValidId($fields);

        $item = CallbackRequestTable::getRowById($id);
        if ($item === null) {
            throw new RestException(Loc::getMessage('OTUS_CALLBACK_REQUESTS_ERR_NOT_FOUND'), 'NOT_FOUND');
        }

        $item = self::formatItem($item);

        Logger::log('otus.callback.request.get', $params, $item);

        return $item;
    }

    /**
     * @param array $params
     * @param mixed $n
     * @param CRestServer $server
     *
     * @return array
     */
    public static function list(array $params, $n, CRestServer $server): array
    {
        $fields = array_change_key_case($params, CASE_UPPER);
        $requestFilter = array_change_key_case($fields['FILTER'] ?? [], CASE_UPPER);

        $filter = [];
        if (!empty($requestFilter['STATUS'])) {
            $filter['=STATUS'] = $requestFilter['STATUS'];
        }

        $start = (int) ($fields['START'] ?? 0);

        $items = CallbackRequestTable::getList([
            'select' => ['ID', 'NAME', 'PHONE', 'COMMENT', 'STATUS', 'DATE_CREATE'],
            'filter' => $filter,
            'order' => ['ID' => 'DESC'],
            'limit' => self::PAGE_SIZE,
            'offset' => $start,
        ])->fetchAll();

        $result = ['items' => array_map([self::class, 'formatItem'], $items)];

        Logger::log('otus.callback.request.list', $params, $result);

        return $result;
    }

    /**
     * @param array $params
     * @param mixed $n
     * @param CRestServer $server
     *
     * @return array
     * @throws RestException
     */
    public static function update(array $params, $n, CRestServer $server): array
    {
        $fields = array_change_key_case($params, CASE_UPPER);
        $id = self::assertValidId($fields);

        if (CallbackRequestTable::getRowById($id) === null) {
            throw new RestException(Loc::getMessage('OTUS_CALLBACK_REQUESTS_ERR_NOT_FOUND'), 'NOT_FOUND');
        }

        $requestFields = array_change_key_case($fields['FIELDS'] ?? [], CASE_UPPER);

        $updateFields = [];
        foreach (['NAME', 'PHONE', 'COMMENT', 'STATUS'] as $fieldName) {
            if (array_key_exists($fieldName, $requestFields)) {
                $updateFields[$fieldName] = $requestFields[$fieldName];
            }
        }

        if (isset($updateFields['STATUS'])) {
            self::assertStatusIsAllowed($updateFields['STATUS']);
        }

        if (empty($updateFields)) {
            throw new RestException(Loc::getMessage('OTUS_CALLBACK_REQUESTS_ERR_EMPTY_FIELDS'), 'EMPTY_FIELDS');
        }

        $updateResult = CallbackRequestTable::update($id, $updateFields);
        if (!$updateResult->isSuccess()) {
            throw new RestException(implode('; ', $updateResult->getErrorMessages()), 'UPDATE_ERROR');
        }

        $result = ['ID' => $id];

        Logger::log('otus.callback.request.update', $params, $result);

        return $result;
    }

    /**
     * @param array $params
     * @param mixed $n
     * @param CRestServer $server
     *
     * @return array
     * @throws RestException
     */
    public static function delete(array $params, $n, CRestServer $server): array
    {
        $fields = array_change_key_case($params, CASE_UPPER);
        $id = self::assertValidId($fields);

        if (CallbackRequestTable::getRowById($id) === null) {
            throw new RestException(Loc::getMessage('OTUS_CALLBACK_REQUESTS_ERR_NOT_FOUND'), 'NOT_FOUND');
        }

        $deleteResult = CallbackRequestTable::delete($id);
        if (!$deleteResult->isSuccess()) {
            throw new RestException(implode('; ', $deleteResult->getErrorMessages()), 'DELETE_ERROR');
        }

        $result = ['ID' => $id];

        Logger::log('otus.callback.request.delete', $params, $result);

        return $result;
    }

    /**
     * @param array $item
     *
     * @return array
     */
    private static function formatItem(array $item): array
    {
        if ($item['DATE_CREATE'] instanceof \Bitrix\Main\Type\DateTime) {
            $item['DATE_CREATE'] = $item['DATE_CREATE']->toString();
        }

        return $item;
    }

    /**
     * @param array $fields
     *
     * @return int
     * @throws RestException
     */
    private static function assertValidId(array $fields): int
    {
        if (empty($fields['ID'])) {
            throw new RestException(Loc::getMessage('OTUS_CALLBACK_REQUESTS_ERR_EMPTY_ID'), 'EMPTY_ID');
        }

        return (int) $fields['ID'];
    }

    /**
     * @param string $status
     *
     * @return void
     * @throws RestException
     */
    private static function assertStatusIsAllowed(string $status): void
    {
        if (!in_array($status, CallbackRequestTable::getAllowedStatuses(), true)) {
            throw new RestException(Loc::getMessage('OTUS_CALLBACK_REQUESTS_ERR_WRONG_STATUS'), 'WRONG_STATUS');
        }
    }
}

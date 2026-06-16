<?php

namespace Otus\Callback\Requests;

use Bitrix\Main\Diag\Debug;

/**
 * Логирование входящих данных и результатов REST-методов модуля.
 */
class Logger
{
    private const LOG_FILE = 'local/logs/otus_callback_requests_rest.log';

    /**
     * @param string $method
     * @param array $params
     * @param mixed $result
     *
     * @return void
     */
    public static function log(string $method, array $params, $result): void
    {
        $logDir = dirname($_SERVER['DOCUMENT_ROOT'] . '/' . self::LOG_FILE);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        Debug::writeToFile(
            [
                'params' => $params,
                'result' => $result,
            ],
            $method,
            self::LOG_FILE
        );
    }
}

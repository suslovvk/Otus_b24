<?php
/**
 * Кастомный логгер ошибок для Bitrix24 с префиксом "OTUS".
 * 
 * Наследует стандартный файловый логгер Bitrix и модифицирует формат записей.
 * 
 * @package Otus\Logger
 * @see \Bitrix\Main\Diag\FileExceptionHandlerLog
 */

 class LogForOtus extends \Bitrix\Main\Diag\FileExceptionHandlerLog
{
   private $logFile = 'local/logs/otus_errors.log';
   private $maxLogSize = 10485760; // 10 MB

    public function initialize(array $settings) 
    {
        if (!empty($settings['file'])) {
            $this->logFile = $settings['file'];
        }
        if (!empty($settings['log_size'])) {
            $this->maxLogSize = (int)$settings['log_size'];
        }
    }

/**
 * Запись ошибки/исключения в лог
 *
 * @param \Throwable $exception Исключение или ошибка
 * @param string $logType Тип ошибки (например, 'UNCAUGHT_EXCEPTION')
 * @return void
 *
 * @uses self::formatMessage() Для форматирования записи
 */

 public function write($exception, $logType)
    {
        $message = sprintf(
            "[OTUS] %s | %s | %s (%s:%d)\n",
            date('Y-m-d H:i:s'),
            $this->logTypeToString($logType),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        file_put_contents(
            $_SERVER['DOCUMENT_ROOT'].'/'.$this->logFile,
            $message,
            FILE_APPEND
        );
    }
}
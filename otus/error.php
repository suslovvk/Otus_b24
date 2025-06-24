<?php
require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';

// Тест 1: Исключение
try {
    $a = 1 / 0;
} catch (Throwable $e) {
    $handler = \Bitrix\Main\Application::getInstance()->getExceptionHandler();
    $handler->writeToLog($e, 'UNCAUGHT_EXCEPTION');
}


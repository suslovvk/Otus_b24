<?php

$path = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($path)) {
    die("Файл автозагрузчика compose не найден по пути: " . $path);
}
require $path;

$pathAutoload = __DIR__ . '/../app/autoload.php';
if (!file_exists($pathAutoload)) {
    die("Файл автозагрузчика compose не найден по пути: " . $path);
}
require $pathAutoload;
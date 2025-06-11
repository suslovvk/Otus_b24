<?php

$path = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($path)) {
    die("Файл автозагрузчика не найден по пути: " . $path);
}
require $path;

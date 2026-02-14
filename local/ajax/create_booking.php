<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;

// Проверяем, что это POST запрос
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    die();
}

Loader::includeModule('iblock');

// Получаем данные из запроса
$procedureId = intval($_POST['procedureId']);
$doctorId = intval($_POST['doctorId']);
$patientName = trim($_POST['patientName']);
$bookingTime = trim($_POST['bookingTime']);

// Преобразуем дату из формата ISO в формат Битрикс
$dateTime = new DateTime($bookingTime);
$bookingTimeFormatted = $dateTime->format('d.m.Y H:i:s');


// Валидация
if (empty($procedureId) || empty($doctorId) || empty($patientName) || empty($bookingTime)) {
    echo json_encode(['success' => false, 'error' => 'Заполните все поля']);
    die();
}

// Создаем элемент в инфоблоке Бронирование
$el = new CIBlockElement;

$arFields = [
    'IBLOCK_ID' => 21, // ID инфоблока Бронирование
    'NAME' => 'Бронирование: ' . $patientName . ' - ' . date('d.m.Y H:i', strtotime($bookingTime)),
    'ACTIVE' => 'Y',
    'PROPERTY_VALUES' => [
        'PATIENT_NAME' => $patientName,
        'BOOKING_TIME' => $bookingTimeFormatted,
        'PROCEDURE' => $procedureId,
        'DOCTOR' => $doctorId,
    ]
];

$elementId = $el->Add($arFields);

if ($elementId) {
    echo json_encode([
        'success' => true,
        'elementId' => $elementId,
        'message' => 'Бронирование успешно создано'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => $el->LAST_ERROR
    ]);
}
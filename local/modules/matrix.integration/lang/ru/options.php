<?php

$MESS['MATRIX_OPTIONS_ACCESS_DENIED'] = 'Доступ запрещён';
$MESS['MATRIX_OPTIONS_TAB_API'] = 'Настройки API';
$MESS['MATRIX_OPTIONS_TAB_API_TITLE'] = 'Параметры подключения к Matrix';
$MESS['MATRIX_OPTIONS_SAVED'] = 'Настройки успешно сохранены.';
$MESS['MATRIX_OPTIONS_SECTION_API'] = 'Подключение к Matrix';
$MESS['MATRIX_OPTIONS_BASE_URL_LABEL'] = 'Base URL';
$MESS['MATRIX_OPTIONS_BASE_URL_HINT'] = 'Базовый URL API Matrix . Пример: https://domain.matrix.ru/api/v1/';
$MESS['MATRIX_OPTIONS_API_KEY_LABEL'] = 'API Key';
$MESS['MATRIX_OPTIONS_API_KEY_PLACEHOLDER'] = 'Введите ключ доступа к Matrix ';
$MESS['MATRIX_OPTIONS_API_KEY_PLACEHOLDER_SAVED'] = 'Ключ сохранён — оставьте пустым или введите новый';
$MESS['MATRIX_OPTIONS_SECTION_NOTIFY'] = 'Уведомления';
$MESS['MATRIX_OPTIONS_RESPONSIBLE_LABEL'] = 'ID ответственного менеджера';
$MESS['MATRIX_OPTIONS_RESPONSIBLE_HINT'] = 'ID пользователя Битрикс24, который будет получать системные уведомления модуля';
$MESS['MATRIX_OPTIONS_SAVE_BUTTON'] = 'Сохранить';
$MESS['MATRIX_OPTIONS_SECTION_BLOCK2'] = 'Справочники (блок 2): каталог Matrix → CRM';
$MESS['MATRIX_OPTIONS_CATALOG_IBLOCK_LABEL'] = 'ID инфоблока каталога';
$MESS['MATRIX_OPTIONS_CATALOG_IBLOCK_HINT'] = '0 — каталог товаров CRM по умолчанию (если доступен модуль crm). Иначе укажите ID инфоблока с привязкой к каталогу.';
$MESS['MATRIX_OPTIONS_BASE_CURRENCY_LABEL'] = 'Валюта базовой цены';
$MESS['MATRIX_OPTIONS_DOCTOR_GROUPS_LABEL'] = 'Группы для новых врачей (ID через запятую)';
$MESS['MATRIX_OPTIONS_DOCTOR_GROUPS_HINT'] = 'Нужны для автосоздания пользователей врачей из Matrix. Если пусто — новые врачи не создаются (только сопоставление с существующими по ФИО).';
$MESS['MATRIX_OPTIONS_STORE_MAP_LABEL'] = 'Маппинг складов Matrix → Битрикс';
$MESS['MATRIX_OPTIONS_MATRIX_STORES_HINT'] = 'Склады из Matrix (подсказка по ID; сохраните настройки после проверки API):';
$MESS['MATRIX_OPTIONS_STORE_M_HEADER'] = 'Склад Matrix (ID)';
$MESS['MATRIX_OPTIONS_STORE_B_HEADER'] = 'Склад Битрикс';
$MESS['MATRIX_OPTIONS_SYNC_REFERENCE_BTN'] = 'Синхронизировать справочники';
$MESS['MATRIX_OPTIONS_DEBUG_MODE_LOG_NOTE'] = 'Включайте только для диагностики. Каталог логов:';

// Вкладки
$MESS['MATRIX_OPTIONS_TAB_API'] = 'Настройки API';
$MESS['MATRIX_OPTIONS_TAB_API_TITLE'] = 'Подключение к Matrix CRM';
$MESS['MATRIX_OPTIONS_TAB_CATALOG'] = 'Синхронизация каталога';
$MESS['MATRIX_OPTIONS_TAB_CATALOG_TITLE'] = 'Настройки синхронизации товаров из Matrix';
$MESS['MATRIX_OPTIONS_TAB_DOCTORS'] = 'Синхронизация врачей';
$MESS['MATRIX_OPTIONS_TAB_DOCTORS_TITLE'] = 'Настройки синхронизации врачей';

// Настройки каталога
$MESS['MATRIX_OPTIONS_CATALOG_SETTINGS'] = 'Настройки каталога товаров';
$MESS['MATRIX_OPTIONS_CATALOG_IBLOCK_LABEL'] = 'Инфоблок каталога';
$MESS['MATRIX_OPTIONS_CATALOG_IBLOCK_SELECT'] = '— Выберите инфоблок —';
$MESS['MATRIX_OPTIONS_CATALOG_IBLOCK_HINT'] = 'Инфоблок типа "Каталог товаров", в который будут синхронизироваться услуги из Matrix';
$MESS['MATRIX_OPTIONS_BASE_CURRENCY_LABEL'] = 'Базовая валюта';
$MESS['MATRIX_OPTIONS_BASE_CURRENCY_HINT'] = 'Валюта, в которой будут создаваться товары';
$MESS['MATRIX_OPTIONS_SYNC_ACTIONS'] = 'Действия с каталогом';
$MESS['MATRIX_OPTIONS_SYNC_CATALOG_BTN'] = 'Запустить синхронизацию товаров';
$MESS['MATRIX_OPTIONS_SYNC_CATALOG_HINT'] = 'Загружает товары из Matrix CRM (/prices) и создаёт/обновляет их в выбранном инфоблоке';

// Настройки врачей (добавим позже)
$MESS['MATRIX_OPTIONS_DOCTORS_SETTINGS'] = 'Настройки синхронизации врачей';
$MESS['MATRIX_OPTIONS_DOCTOR_GROUPS_LABEL'] = 'Группы пользователей для врачей';
$MESS['MATRIX_OPTIONS_DOCTOR_GROUPS_HINT'] = 'Группы, в которые будут добавляться создаваемые врачи (ID через запятую)';
$MESS['MATRIX_OPTIONS_SYNC_DOCTORS_BTN'] = 'Запустить синхронизацию врачей';
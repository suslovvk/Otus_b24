<?php

namespace Otus\Dz11;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__DIR__ . '/../handler.php');

/**
 * Обработчик REST-событий локального приложения «Дата последней коммуникации».
 *
 * Обрабатывает два события:
 * - ONAPPINSTALL: первичная установка приложения, регистрирует подписку на события.
 * - ONCRMTIMELINECOMMENTADD: при добавлении комментария в таймлайне CRM обновляет
 *   поле {@see self::FIELD_LAST_COMMUNICATION} в карточке контакта через REST API.
 */
class EventHandler
{
    /** Событие первичной установки приложения */
    private const EVENT_INSTALL = 'ONAPPINSTALL';

    /** Событие добавления комментария в таймлайне CRM */
    private const EVENT_COMMENT = 'ONCRMTIMELINECOMMENTADD';

    /** Тип CRM-сущности «Контакт» (значение из crm.timeline.comment.get, в нижнем регистре) */
    private const ENTITY_TYPE_CONTACT = 'contact';

    /** Имя пользовательского поля для хранения даты последней коммуникации */
    private const FIELD_LAST_COMMUNICATION = 'UF_CRM_LAST_COMMUNICATION';

    /** Путь к файлу лога относительно корня сайта */
    private const LOG_FILE = '/local/apps/dz11/logs/handler.log';

    /** URL обработчика событий (должен быть доступен из Битрикс24) */
    private const HANDLER_URL = 'http://otus.dev.sunweb.pro/local/apps/dz11/handler.php';

    /**
     * Точка входа: определяет тип входящего запроса и вызывает нужный обработчик.
     *
     * @return void
     */
    public function process(): void
    {
        $event = $_REQUEST['event'] ?? '';

        if (!empty($_REQUEST['auth']) && is_array($_REQUEST['auth'])) {
            $this->saveAuth($_REQUEST['auth']);
        } elseif (!empty($_REQUEST['AUTH_ID']) && !empty($_REQUEST['DOMAIN'])) {
            $this->saveAuthFromPlacement();
        }

        if ($event === self::EVENT_INSTALL) {
            $this->handleInstall();
        } elseif ($event === self::EVENT_COMMENT) {
            $this->handleCommentEvent();
        } else {
            $this->log(Loc::getMessage('DZ11_HANDLER_WRONG_EVENT', ['#EVENT#' => $event]));
        }
    }

    /**
     * Сохраняет OAuth-токены из параметров открытия страницы приложения (PLACEMENT=DEFAULT).
     *
     * Битрикс24 передаёт токены как отдельные параметры AUTH_ID, REFRESH_ID и т.д.,
     * а не в массиве auth — этот метод нормализует их в единый формат и сохраняет.
     *
     * @return void
     */
    private function saveAuthFromPlacement(): void
    {
        $this->saveAuth([
            'access_token'      => (string)($_REQUEST['AUTH_ID'] ?? ''),
            'expires_in'        => (int)($_REQUEST['AUTH_EXPIRES'] ?? 3600),
            'refresh_token'     => (string)($_REQUEST['REFRESH_ID'] ?? ''),
            'domain'            => (string)($_REQUEST['DOMAIN'] ?? ''),
            'application_token' => (string)($_REQUEST['APP_SID'] ?? ''),
            'client_endpoint'   => 'https://' . (string)($_REQUEST['DOMAIN'] ?? '') . '/rest/',
        ]);
        $this->log(Loc::getMessage('DZ11_HANDLER_PLACEMENT_AUTH'));
    }

    /**
     * Сохраняет OAuth-токены из запроса в файл настроек CRest (settings.json).
     *
     * При локальной установке Битрикс24 передаёт HTTPS-эндпоинт. Для корректной
     * работы внутри Docker-сети эндпоинт конвертируется в HTTP.
     *
     * @param array $auth Массив авторизационных данных из $_REQUEST['auth'].
     *
     * @return void
     */
    private function saveAuth(array $auth): void
    {
        if (!empty($auth['client_endpoint'])) {
            $endpoint = (string)$auth['client_endpoint'];
            $auth['client_endpoint'] = str_replace('https://', 'http://', $endpoint);
        }

        $settingsFile = __DIR__ . '/../settings.json';
        $existing     = [];

        if (file_exists($settingsFile)) {
            $decoded  = json_decode((string)file_get_contents($settingsFile), true);
            $existing = is_array($decoded) ? $decoded : [];
        }

        file_put_contents($settingsFile, json_encode(array_merge($existing, $auth)));
    }

    /**
     * Обрабатывает событие установки приложения ONAPPINSTALL.
     *
     * Регистрирует обработчик события ONCRM_TIMELINE_COMMENT_ADD через REST API.
     *
     * @return void
     */
    private function handleInstall(): void
    {
        $result = \CRest::call('event.bind', [
            'event'   => self::EVENT_COMMENT,
            'handler' => self::HANDLER_URL,
        ]);

        if (!empty($result['result'])) {
            $this->log(Loc::getMessage('DZ11_HANDLER_INSTALL_OK'));
        } else {
            $this->log(Loc::getMessage('DZ11_HANDLER_INSTALL_ERROR', [
                '#ERROR#' => $result['error'] ?? json_encode($result),
            ]));
        }
    }

    /**
     * Обрабатывает событие добавления комментария в таймлайне CRM.
     *
     * REST-событие ONCRMTIMELINECOMMENTADD передаёт только ID записи комментария
     * (data.FIELDS.ID), без ENTITY_TYPE/ENTITY_ID — их нужно получить дополнительным
     * вызовом crm.timeline.comment.get. Проверяет тип сущности (только Контакт)
     * и запускает обновление поля.
     *
     * @return void
     */
    private function handleCommentEvent(): void
    {
        $commentId = (int)($_REQUEST['data']['FIELDS']['ID'] ?? 0);

        if ($commentId <= 0) {
            $this->log(Loc::getMessage('DZ11_HANDLER_SKIP_ENTITY'));
            return;
        }

        $comment = \CRest::call('crm.timeline.comment.get', ['id' => $commentId]);

        $entityType = $comment['result']['ENTITY_TYPE'] ?? '';
        $entityId   = (int)($comment['result']['ENTITY_ID'] ?? 0);

        if ($entityType !== self::ENTITY_TYPE_CONTACT || $entityId <= 0) {
            $this->log(Loc::getMessage('DZ11_HANDLER_SKIP_ENTITY'));
            return;
        }

        $this->updateContact($entityId);
    }

    /**
     * Обновляет поле «Дата последней коммуникации» контакта через REST API.
     *
     * Использует CRest SDK для вызова crm.contact.update.
     *
     * @param int $contactId Идентификатор контакта в CRM.
     *
     * @return void
     */
    private function updateContact(int $contactId): void
    {
        $datetime = date('d.m.Y H:i:s');

        $result = \CRest::call('crm.contact.update', [
            'id'     => $contactId,
            'fields' => [self::FIELD_LAST_COMMUNICATION => $datetime],
        ]);

        if (isset($result['result']) && $result['result'] === true) {
            $this->log(Loc::getMessage('DZ11_HANDLER_SUCCESS', ['#ID#' => $contactId]));
        } else {
            $this->log(Loc::getMessage('DZ11_HANDLER_API_ERROR', [
                '#ID#'    => $contactId,
                '#ERROR#' => $result['error_description'] ?? ($result['error'] ?? json_encode($result)),
            ]));
        }
    }

    /**
     * Записывает строку в лог-файл обработчика.
     *
     * @param string $message Текст сообщения.
     *
     * @return void
     */
    private function log(string $message): void
    {
        $line = date('Y-m-d H:i:s') . ' | ' . $message . PHP_EOL;
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . self::LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    }
}

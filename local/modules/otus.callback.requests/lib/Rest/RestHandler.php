<?php

namespace Otus\Callback\Requests\Rest;

/**
 * Регистрирует REST-методы модуля в обработчике расширения REST-сервиса.
 */
class RestHandler extends \IRestService
{
    /**
     * @return array
     */
    public static function onRestServiceBuildDescription(): array
    {
        return [
            'otus.callback.request' => [
                'otus.callback.request.add' => [CallbackRequestService::class, 'add'],
                'otus.callback.request.get' => [CallbackRequestService::class, 'get'],
                'otus.callback.request.list' => [CallbackRequestService::class, 'list'],
                'otus.callback.request.update' => [CallbackRequestService::class, 'update'],
                'otus.callback.request.delete' => [CallbackRequestService::class, 'delete'],
            ],
        ];
    }
}

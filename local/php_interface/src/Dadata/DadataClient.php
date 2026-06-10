<?php

namespace Otus\Dadata;

use Bitrix\Main\Web\HttpClient;

/**
 * HTTP-клиент для работы с API сервиса DaData.
 */
class DadataClient
{
    private const API_FIND_BY_INN = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party';

    private string $apiKey;

    /**
     * @param string $apiKey Токен DaData. Если не передан — берётся из константы DADATA_API_KEY.
     */
    public function __construct(string $apiKey = '')
    {
        $this->apiKey = $apiKey !== '' ? $apiKey : (defined('DADATA_API_KEY') ? DADATA_API_KEY : '');
    }

    /**
     * Возвращает данные компании по ИНН через API DaData.
     * При отсутствии результата или ошибке запроса возвращает null.
     *
     * @param string $inn
     *
     * @return array|null
     */
    public function getCompanyByInn(string $inn): ?array
    {
        $inn = trim($inn);

        if ($inn === '' || $this->apiKey === '') {
            return null;
        }

        $http = new HttpClient([
            'socketTimeout' => 5,
            'streamTimeout' => 15,
        ]);

        $http->setHeader('Content-Type', 'application/json');
        $http->setHeader('Accept', 'application/json');
        $http->setHeader('Authorization', 'Token ' . $this->apiKey);

        $body     = json_encode(['query' => $inn, 'count' => 1]);
        $response = $http->post(self::API_FIND_BY_INN, $body);

        if ($http->getStatus() !== 200 || !$response) {
            return null;
        }

        $data       = json_decode($response, true);
        $suggestion = $data['suggestions'][0] ?? null;

        if (!is_array($suggestion)) {
            return null;
        }

        $companyData = $suggestion['data'] ?? [];

        return [
            'shortName' => $companyData['name']['short_with_opf'] ?? $suggestion['value'] ?? '',
            'fullName'  => $companyData['name']['full_with_opf']  ?? $suggestion['value'] ?? '',
            'inn'       => $companyData['inn']  ?? $inn,
            'kpp'       => $companyData['kpp']  ?? '',
            'ogrn'      => $companyData['ogrn'] ?? '',
            'address'   => $companyData['address']['value'] ?? '',
        ];
    }
}

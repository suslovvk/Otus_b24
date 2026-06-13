<?php

namespace Matrix\Integration\Api;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;

/**
 * HTTP-клиент для работы с Matrix CRM API.
 * Методы выбрасывают \RuntimeException при ошибке сети или не-2xx ответе.
 */
class MatrixClient
{
    private const OPTION_BASE_URL = 'MATRIX_BASE_URL';
    private const OPTION_API_KEY = 'MATRIX_API_KEY';
    private const OPTION_DEBUG_MODE = 'MATRIX_DEBUG_MODE';

    private const DEFAULT_TIMEOUT_SOCKET = 30;
    private const DEFAULT_TIMEOUT_STREAM = 60;
    private const PAGINATION_LIMIT = 100;

    /** Относительно DOCUMENT_ROOT, см. ТЗ (bitrix/php_interface/log/) */
    private const LOG_FILE_RELATIVE = 'bitrix/php_interface/log/matrix_integration.log';

    /** Эндпоинт для health-check — пациент с известным ID */
    private const PING_ENDPOINT = 'patients/1';

    private string $baseUrl;
    private string $apiKey;
    private bool   $isDebug;

    public function __construct()
    {
        $this->baseUrl = (string) Option::get('matrix.integration', self::OPTION_BASE_URL);
        $this->apiKey  = (string) Option::get('matrix.integration', self::OPTION_API_KEY);
        $this->isDebug = Option::get('matrix.integration', self::OPTION_DEBUG_MODE, 'N') === 'Y';
    }

    /** @param array<string, mixed> $context */
    private static function writeLog(string $title, array $context = []): void
    {
        $full = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/' . self::LOG_FILE_RELATIVE;
        $dir = dirname($full);
        if ($dir !== '' && !is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        Debug::writeToFile($context, '[' . date('c') . '] ' . $title, self::LOG_FILE_RELATIVE);
    }

    /** @throws \RuntimeException */
    public function get(string $endpoint, array $params = []): array
    {
        return $this->request('GET', $endpoint, $params);
    }

    /** @throws \RuntimeException */
    public function post(string $endpoint, array $body = []): array
    {
        return $this->request('POST', $endpoint, [], $body);
    }

    /** @throws \RuntimeException */
    public function put(string $endpoint, array $body = []): array
    {
        return $this->request('PUT', $endpoint, [], $body);
    }

    /** @throws \RuntimeException */
    public function delete(string $endpoint, array $params = []): array
    {
        return $this->request('DELETE', $endpoint, $params);
    }

    /** @throws \RuntimeException|\InvalidArgumentException */
    private function request(string $method, string $endpoint, array $params = [], array $body = []): array
    {
        if (!$this->baseUrl || !$this->apiKey) {
            throw new \RuntimeException('Matrix CRM API не настроен в настройках модуля');
        }

        // Валидация эндпоинта: защита от path traversal и инъекций
        $endpoint = trim($endpoint, '/');
        if ($endpoint === '' || preg_match('#[?&#]|//|\.\.#', $endpoint)) {
            throw new \InvalidArgumentException('Invalid endpoint: ' . $endpoint);
        }

        $url = rtrim($this->baseUrl, '/') . '/' . $endpoint;
        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        $httpClient = new HttpClient([
            'socketTimeout' => self::DEFAULT_TIMEOUT_SOCKET,
            'streamTimeout' => self::DEFAULT_TIMEOUT_STREAM,
        ]);
        $httpClient->setHeaders([
            'x-api-key' => $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'BitrixMatrixIntegration/1.0',
        ]);

        try {
            $httpClient->query($method, $url, Json::encode($body));

            $statusCode = $httpClient->getStatus();
            $rawBody = $httpClient->getResult();

            if ($this->isDebug) {
                self::writeLog('Matrix API request', [
                    'method' => $method,
                    'url' => $url,
                    'status' => $statusCode,
                    'request_body' => $body ? Json::encode($body) : null,
                    'response_preview' => mb_substr($rawBody, 0, 500),
                ]);
            }

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \RuntimeException(sprintf(
                    'Matrix API error [%d]: %s',
                    $statusCode,
                    mb_substr(strip_tags($rawBody), 0, 300)
                ));
            }

            if ($statusCode === 204 || trim($rawBody) === '') {
                return [];
            }

            $data = Json::decode($rawBody);

            if (!is_array($data)) {
                throw new \RuntimeException('Invalid JSON response structure');
            }

            $extracted = $data['data'] ?? $data;

            // balance/bonus-balance возвращают скалярный data — оборачиваем
            if (!is_array($extracted)) {
                return ['_scalar' => $extracted];
            }

            return $extracted;

        } catch (\Exception $e) {
            self::writeLog('Matrix API request failed', [
                'method' => $method,
                'url' => $url,
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'code' => $e->getCode(),
            ]);
            throw new \RuntimeException('Matrix API request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Автоматически обходит пагинацию, отдаёт по одному элементу.
     *
     * @param array $baseParams Query-параметры без page/limit
     * @return \Generator
     */
    public function paginate(string $endpoint, array $baseParams = []): \Generator
    {
        $page    = 1;
        $limit   = self::PAGINATION_LIMIT;
        $maxPage = 200;

        while ($page <= $maxPage) {
            $params = array_merge($baseParams, [
                'page'  => $page,
                'limit' => $limit,
            ]);

            try {
                $data = $this->get($endpoint, $params);

                if (!is_array($data)) {
                    break;
                }

                $items = $data['items'] ?? $data['data'] ?? $data;

                if (!is_array($items) || empty($items)) {
                    break;
                }

                foreach ($items as $item) {
                    yield $item;
                }

                // Matrix API не поддерживает пагинацию — игнорирует параметр page
                // и всегда возвращает одни и те же записи. Признак последней страницы:
                // вернулось меньше элементов чем запрошено.
                if (count($items) < $limit) {
                    break;
                }

                $page++;

            } catch (\RuntimeException $e) {
                self::writeLog('Matrix pagination interrupted', [
                    'endpoint' => $endpoint,
                    'page'     => $page,
                    'error'    => $e->getMessage(),
                ]);
                break;
            }
        }
    }

    /**
     * Проверка доступности API. 404 с JSON-телом считается успехом
     * (URL верный, пациент #1 просто отсутствует).
     */
    public function ping(): bool
    {
        if (!$this->baseUrl || !$this->apiKey) {
            return false;
        }

        $url = rtrim($this->baseUrl, '/') . '/' . self::PING_ENDPOINT;

        $httpClient = new HttpClient([
            'socketTimeout' => self::DEFAULT_TIMEOUT_SOCKET,
            'streamTimeout' => self::DEFAULT_TIMEOUT_STREAM,
        ]);
        $httpClient->setHeaders([
            'x-api-key'    => $this->apiKey,
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        try {
            $httpClient->query('GET', $url);
            $status  = $httpClient->getStatus();
            $rawBody = $httpClient->getResult();

            if ($status >= 200 && $status < 300) {
                return true;
            }

            if ($status === 404) {
                $decoded = json_decode($rawBody, true);
                $isMatrixJson = is_array($decoded);
                self::writeLog('Matrix ping: 404 response', [
                    'url'          => $url,
                    'is_json'      => $isMatrixJson,
                    'body_preview' => mb_substr($rawBody, 0, 200),
                ]);
                return $isMatrixJson;
            }

            if ($status === 401 || $status === 403) {
                self::writeLog('Matrix ping: auth error', ['status' => $status]);
                return false;
            }

            self::writeLog('Matrix ping: unexpected status', ['status' => $status, 'url' => $url]);
            return false;

        } catch (\Throwable $e) {
            self::writeLog('Matrix ping failed', ['error' => $e->getMessage(), 'url' => $url]);
            return false;
        }
    }
}
<?php

namespace Matrix\Integration\Sync;

use Bitrix\Catalog\StoreProductTable;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Loader;
use CCatalogProduct;
use CPrice;
use CIBlockElement;
use CIBlockSection;
use Matrix\Integration\Api\MatrixClient;
use Matrix\Integration\Orm\ProcedureTable;
use Matrix\Integration\Util\ApiItem;

/**
 * Синхронизация физических товаров из Matrix CRM.
 * 
 * Эндпоинты API:
 * - GET /api/v1/products/groups    — группы товаров
 * - GET /api/v1/stores/{id}/balance — товары и остатки по складам
 * 
 * @package Matrix\Integration\Sync
 */
class ProductSync
{
    private const MODULE_ID = 'matrix.integration';
    private const XML_GROUP_PREFIX = 'MXGRP_';
    private const XML_PRODUCT_PREFIX = 'MXPRODUCT_';
    private const LOG_FILE = '/upload/product_sync.log';

    private array $log = [];

    public function __construct(
        private readonly MatrixClient $client
    ) {
    }

    /**
     * Добавляет сообщение в лог
     */
    private function addLog(string $message): void
    {
        $this->log[] = $message;
        
        $logFile = $_SERVER['DOCUMENT_ROOT'] . self::LOG_FILE;
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
        
        // При включенном debug режиме дублируем в системный лог
        $debugMode = Option::get(self::MODULE_ID, 'MATRIX_DEBUG_MODE', 'N') === 'Y';
        if ($debugMode) {
            Debug::writeToFile(
                ['message' => $message, 'timestamp' => date('Y-m-d H:i:s')],
                '[ProductSync]',
                '/bitrix/php_interface/log/matrix_integration.log'
            );
        }
    }

    public function getLog(): array
    {
        return $this->log;
    }

    /**
     * Полная синхронизация товаров: группы → товары + остатки (один проход)
     *
     * @return array{
     *     groups: int,
     *     products: int,
     *     balances: int,
     *     warnings: list<string>,
     *     errors: list<string>
     * }
     */
    public function syncAll(): array
    {
        $this->addLog('=== НАЧАЛО syncAll (Вариант Б - объединенный проход) ===');
        
        $result = [
            'groups' => 0,
            'products' => 0,
            'balances' => 0,
            'warnings' => [],
            'errors' => [],
        ];

        if (!Loader::includeModule('iblock')) {
            $result['errors'][] = 'Модуль iblock не подключён';
            return $result;
        }
        if (!Loader::includeModule('catalog')) {
            $result['errors'][] = 'Модуль catalog не подключён';
            return $result;
        }

        // ШАГ 1: Синхронизируем группы (отдельно, т.к. другой эндпоинт)
        try {
            $this->addLog('ШАГ 1: Синхронизация групп');
            $result['groups'] = $this->syncGroups();
            $this->addLog('Создано/обновлено групп: ' . $result['groups']);
        } catch (\Throwable $e) {
            $this->addLog('ОШИБКА групп: ' . $e->getMessage());
            $result['errors'][] = 'Группы: ' . $e->getMessage();
        }

        // ШАГ 2: Синхронизируем товары и остатки (ОДИН проход по складам)
        try {
            $this->addLog('ШАГ 2: Синхронизация товаров и остатков (объединенный проход)');
            $syncResult = $this->syncProductsWithBalances();
            $result['products'] = $syncResult['products'];
            $result['balances'] = $syncResult['balances'];
            $this->addLog("Создано/обновлено товаров: {$result['products']}");
            $this->addLog("Обновлено остатков: {$result['balances']}");
            foreach ($syncResult['warnings'] as $w) {
                $result['warnings'][] = $w;
            }
            foreach ($syncResult['errors'] as $e) {
                $result['errors'][] = $e;
            }
        } catch (\Throwable $e) {
            $this->addLog('ОШИБКА товаров/остатков: ' . $e->getMessage());
            $result['errors'][] = 'Товары/остатки: ' . $e->getMessage();
        }

        $this->addLog('=== КОНЕЦ syncAll ===');
        
        return $result;
    }

    /**
     * Получение маппинга складов
     */
    private function getStoreMap(): array
    {
        $mapJson = (string) Option::get(self::MODULE_ID, 'MATRIX_STORE_MAP', '{}');
        $map = json_decode($mapJson, true);
        return is_array($map) ? $map : [];
    }

    /**
     * Получение ID единицы измерения по умолчанию
     */
    private function getDefaultMeasureId(): int
    {
        $measure = \CCatalogMeasure::getDefaultMeasure(true, true);
        return is_array($measure) && !empty($measure['ID']) ? (int) $measure['ID'] : 0;
    }

    private function getCatalogIblockId(): int
    {
        $fromOpt = (int) Option::get(self::MODULE_ID, 'MATRIX_CATALOG_IBLOCK_ID', '0');
        $this->addLog('getCatalogIblockId: из опций = ' . $fromOpt);
        
        if ($fromOpt > 0) {
            return $fromOpt;
        }

        if (Loader::includeModule('crm') && class_exists(\Bitrix\Crm\Product\Catalog::class)) {
            $id = \Bitrix\Crm\Product\Catalog::getDefaultId();
            if ($id !== null && $id > 0) {
                $this->addLog('getCatalogIblockId: из CRM = ' . $id);
                Option::set(self::MODULE_ID, 'MATRIX_CATALOG_IBLOCK_ID', (string) $id);
                return (int) $id;
            }
        }

        throw new \RuntimeException(
            'Не найден инфоблок каталога: укажите ID в настройках (MATRIX_CATALOG_IBLOCK_ID)'
        );
    }

    private function getBaseCurrency(): string
    {
        $c = trim((string) Option::get(self::MODULE_ID, 'MATRIX_BASE_CURRENCY', 'RUB'));
        return $c !== '' ? $c : 'RUB';
    }

    private function groupXmlId(int $matrixGroupId): string
    {
        return self::XML_GROUP_PREFIX . $matrixGroupId;
    }

    private function productXmlId(int $matrixProductId): string
    {
        return self::XML_PRODUCT_PREFIX . $matrixProductId;
    }

    /**
     * Синхронизация групп (древовидная структура)
     */
    private function syncGroups(): int
    {
        $iblockId = $this->getCatalogIblockId();
        $this->addLog('syncGroups: инфоблок ID=' . $iblockId);

        // Получаем все группы из API
        $groups = [];
        foreach ($this->client->paginate('products/groups') as $row) {
            if (is_array($row)) {
                $groups[] = $row;
            }
        }

        $this->addLog('syncGroups: получено групп из API: ' . count($groups));
        
        if (empty($groups)) {
            return 0;
        }

        // Диагностика только при debug режиме
        $debugMode = Option::get(self::MODULE_ID, 'MATRIX_DEBUG_MODE', 'N') === 'Y';
        if ($debugMode) {
            foreach ($groups as $group) {
                $this->addLog('  Группа из API: id=' . ($group['id'] ?? '?') . 
                             ', title=' . ($group['title'] ?? $group['name'] ?? '?') . 
                             ', parent=' . ($group['parent'] ?? $group['parent_id'] ?? 'null'));
            }
        }

        // Создаем все группы без привязки к родителям
        $groupMap = []; // matrix_id => section_id
        $section = new CIBlockSection();

        foreach ($groups as $group) {
            $matrixId = (int) ($group['id'] ?? 0);
            if ($matrixId <= 0) {
                continue;
            }

            $name = $group['title'] ?? $group['name'] ?? 'Группа #' . $matrixId;
            $xmlId = $this->groupXmlId($matrixId);

            // Проверяем существует ли
            $existing = SectionTable::getRow([
                'filter' => ['=IBLOCK_ID' => $iblockId, '=XML_ID' => $xmlId],
                'select' => ['ID'],
            ]);

            $fields = [
                'IBLOCK_ID' => $iblockId,
                'NAME' => $name,
                'XML_ID' => $xmlId,
                'ACTIVE' => 'Y',
            ];

            if ($existing) {
                $sectionId = (int) $existing['ID'];
                $section->Update($sectionId, $fields);
                $this->addLog("  Группа обновлена: sectionId={$sectionId}");
            } else {
                $sectionId = (int) $section->Add($fields);
                $this->addLog("  Группа создана: sectionId={$sectionId}");
                if ($sectionId <= 0) {
                    $this->addLog("  ОШИБКА создания группы: " . $section->LAST_ERROR);
                }
            }

            if ($sectionId > 0) {
                $groupMap[$matrixId] = $sectionId;
            }
        }

        // Второй проход: устанавливаем родительские связи
        $this->addLog('Установка родительских связей');
        foreach ($groups as $group) {
            $matrixId = (int) ($group['id'] ?? 0);
            $parentMatrixId = (int) ($group['parent'] ?? $group['parent_id'] ?? 0);
            
            if ($parentMatrixId > 0 && isset($groupMap[$matrixId], $groupMap[$parentMatrixId])) {
                $sectionId = $groupMap[$matrixId];
                $parentSectionId = $groupMap[$parentMatrixId];

                $section->Update($sectionId, ['IBLOCK_SECTION_ID' => $parentSectionId]);
            }
        }

        // Перестраиваем структуру
        CIBlockSection::ReSort($iblockId);
        $this->addLog('Перестроена структура разделов');

        return count($groupMap);
    }

    /**
     * Получение ID раздела по Matrix ID группы
     */
    private function getSectionIdByMatrixGroupId(int $iblockId, int $matrixGroupId): int
    {
        if ($matrixGroupId <= 0) {
            return 0;
        }

        $xmlId = $this->groupXmlId($matrixGroupId);
        $section = SectionTable::getRow([
            'filter' => ['=IBLOCK_ID' => $iblockId, '=XML_ID' => $xmlId],
            'select' => ['ID'],
        ]);

        return $section ? (int) $section['ID'] : 0;
    }

    /**
     * Обработка одного товара (создание или обновление)
     * 
     * @return array{product_id:int, product_created:bool, product_updated:bool}
     */
    private function processProduct(array $product, int $iblockId, string $currency, int $measureId): array
    {
        $result = [
            'product_id' => 0,
            'product_created' => false,
            'product_updated' => false,
        ];
        
        $productId = (int) ($product['id'] ?? 0);
        if ($productId <= 0) {
            $this->addLog('Товар пропущен: нет id');
            return $result;
        }
        
        $name = $product['title'] ?? $product['name'] ?? 'Товар #' . $productId;
        $price = (float) ($product['price'] ?? $product['cost'] ?? 0);
        $groupId = (int) ($product['group'] ?? 0);
        
        // Ищем раздел по groupId
        $sectionId = $this->getSectionIdByMatrixGroupId($iblockId, $groupId);
        
        $xmlId = $this->productXmlId($productId);
        $existing = ElementTable::getRow([
            'filter' => ['=IBLOCK_ID' => $iblockId, '=XML_ID' => $xmlId],
            'select' => ['ID'],
        ]);
        
        $el = new CIBlockElement();
        $fields = [
            'IBLOCK_ID' => $iblockId,
            'NAME' => $name,
            'XML_ID' => $xmlId,
            'ACTIVE' => 'Y',
            'IBLOCK_SECTION_ID' => $sectionId > 0 ? $sectionId : false,
        ];
        
        if ($existing) {
            $elId = (int) $existing['ID'];
            $el->Update($elId, $fields);
            $result['product_id'] = $elId;
            $result['product_updated'] = true;
            $this->addLog("  Товар обновлён: Matrix ID={$productId}, elId={$elId}");
        } else {
            $elId = (int) $el->Add($fields);
            if ($elId > 0) {
                $result['product_id'] = $elId;
                $result['product_created'] = true;
                $this->addLog("  Товар создан: Matrix ID={$productId}, elId={$elId}");
            } else {
                $this->addLog("  ОШИБКА создания товара: " . $el->LAST_ERROR);
                return $result;
            }
        }
        
        // Регистрация в каталоге
        if (!CCatalogProduct::IsExistProduct($elId)) {
            CCatalogProduct::Add([
                'ID' => $elId,
                'QUANTITY' => 0,
                'TYPE' => CCatalogProduct::TYPE_PRODUCT,
                'MEASURE' => $measureId,
            ]);
            $this->addLog("  Товар добавлен в каталог");
        }
        
        // Установка цены
        if ($price > 0) {
            CPrice::SetBasePrice($elId, $price, $currency, false, false, true);
            $this->addLog("  Установлена цена: {$price} {$currency}");
        }
        
        return $result;
    }

    /**
     * Обновление остатка товара на складе
     */
    private function updateProductStock(int $bitrixProductId, array $product, int $bitrixStoreId): void
    {
        $quantity = (float) ($product['in-stock'] ?? $product['quantity'] ?? 0);

        $existing = StoreProductTable::getRow([
            'filter' => ['=STORE_ID' => $bitrixStoreId, '=PRODUCT_ID' => $bitrixProductId],
            'select' => ['ID', 'QUANTITY_RESERVED'],
        ]);

        if ($existing) {
            StoreProductTable::update($existing['ID'], ['AMOUNT' => $quantity]);
            $reserved = (float) ($existing['QUANTITY_RESERVED'] ?? 0);
        } else {
            StoreProductTable::add([
                'STORE_ID'          => $bitrixStoreId,
                'PRODUCT_ID'        => $bitrixProductId,
                'AMOUNT'            => $quantity,
                'QUANTITY_RESERVED' => 0,
            ]);
            $reserved = 0.0;
        }

        // StoreProductTable::update() не обновляет b_catalog_product.QUANTITY автоматически.
        // Без явного обновления UI показывает устаревшее значение.
        $catalogQty = max(0.0, $quantity - $reserved);
        \CCatalogProduct::Update($bitrixProductId, ['QUANTITY' => $catalogQty]);

        $this->addLog("    Обновлён остаток: storeId={$bitrixStoreId}, AMOUNT={$quantity}, QUANTITY={$catalogQty}");
    }

    /**
     * Синхронизация товаров и остатков (ОДИН проход по складам)
     * 
     * @return array{products:int, balances:int, warnings:list<string>, errors:list<string>}
     */
    private function syncProductsWithBalances(): array
    {
        $result = [
            'products' => 0,
            'balances' => 0,
            'warnings' => [],
            'errors' => [],
        ];
        
        $iblockId = $this->getCatalogIblockId();
        $currency = $this->getBaseCurrency();
        $measureId = $this->getDefaultMeasureId();
        
        // Получаем маппинг складов
        $storeMap = $this->getStoreMap();
        if (empty($storeMap)) {
            $result['warnings'][] = 'Маппинг складов пуст, используем склад ID=1';
            $storeMap = [1 => 1]; // fallback
        }
        
        $this->addLog("Всего складов для обработки: " . count($storeMap));
        
        // Для каждого склада получаем товары и сразу обновляем остатки
        foreach ($storeMap as $matrixStoreId => $bitrixStoreId) {
            $matrixStoreId = (int) $matrixStoreId;
            $bitrixStoreId = (int) $bitrixStoreId;
            
            if ($matrixStoreId <= 0 || $bitrixStoreId <= 0) {
                $result['warnings'][] = "Пропущен склад: Matrix ID={$matrixStoreId}, Битрикс ID={$bitrixStoreId}";
                continue;
            }
            
            $this->addLog("Обработка склада: Matrix ID={$matrixStoreId} -> Битрикс ID={$bitrixStoreId}");

            // ЕДИНЫЙ запрос к API через MatrixClient
            try {
                $response = $this->client->get('stores/' . $matrixStoreId . '/balance');
            } catch (\Throwable $e) {
                $this->addLog("Ошибка запроса склада {$matrixStoreId}: " . $e->getMessage());
                $result['warnings'][] = "Не удалось получить данные со склада {$matrixStoreId}: " . $e->getMessage();
                continue;
            }

            // MatrixClient уже извлекает ключ 'data' из ответа
            $products = $response['items'] ?? $response['data'] ?? (is_array($response) ? $response : []);
            $this->addLog("Получено товаров со склада: " . count($products));
            
            if (empty($products)) {
                $this->addLog("Нет товаров на складе {$matrixStoreId}");
                continue;
            }
            
            foreach ($products as $product) {
                // 1. Обрабатываем товар (создание/обновление) — ТОЛЬКО 1 РАЗ
                //    Используем ID товара из API как уникальный ключ
                $productResult = $this->processProduct($product, $iblockId, $currency, $measureId);
                
                if ($productResult['product_created'] || $productResult['product_updated']) {
                    $result['products']++;
                }
                
                // 2. Обновляем остаток для этого товара на текущем складе
                if ($productResult['product_id'] > 0) {
                    $this->updateProductStock(
                        $productResult['product_id'],
                        $product,
                        $bitrixStoreId
                    );
                    $result['balances']++;
                }
            }
        }
        
        $this->addLog("syncProductsWithBalances: итого товаров={$result['products']}, остатков={$result['balances']}");
        return $result;
    }

    /**
     * Синхронизирует процедуры из /procedures в ORM-таблицу matrix_procedures.
     *
     * @return array{total:int, created:int, updated:int, errors:list<string>}
     */
    public function syncProcedures(): array
    {
        $this->addLog('=== НАЧАЛО syncProcedures ===');

        $result = ['total' => 0, 'created' => 0, 'updated' => 0, 'errors' => []];

        try {
            foreach ($this->client->paginate('procedures') as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $matrixId = ApiItem::int($item, 0, 'id', 'procedure_id');
                if ($matrixId <= 0) {
                    continue;
                }

                $doctorIds   = $item['doctor_ids'] ?? $item['doctors'] ?? [];
                $cabinetIds  = $item['cabinet_ids'] ?? $item['cabinets'] ?? [];
                $specialtyIds = $item['specialty_ids'] ?? $item['specialties'] ?? [];

                $fields = [
                    'MATRIX_ID'    => $matrixId,
                    'NAME'         => ApiItem::string($item, 'Без названия', 'name', 'title'),
                    'DURATION'     => ApiItem::int($item, 15, 'duration', 'duration_minutes'),
                    'COST'         => ApiItem::float($item, null, 'cost', 'price'),
                    'ONLINE'       => ApiItem::bool($item, false, 'online', 'is_online') ? 1 : 0,
                    'DOCTOR_IDS'   => is_array($doctorIds) ? json_encode($doctorIds) : null,
                    'CABINET_IDS'  => is_array($cabinetIds) ? json_encode($cabinetIds) : null,
                    'SPECIALTY_IDS' => is_array($specialtyIds) ? json_encode($specialtyIds) : null,
                ];

                $existing = ProcedureTable::getRow([
                    'filter' => ['=MATRIX_ID' => $matrixId],
                    'select' => ['ID'],
                ]);

                if ($existing) {
                    ProcedureTable::update($existing['ID'], $fields);
                    $result['updated']++;
                } else {
                    ProcedureTable::add($fields);
                    $result['created']++;
                }

                $result['total']++;
            }
        } catch (\Throwable $e) {
            $this->addLog('ОШИБКА syncProcedures: ' . $e->getMessage());
            $result['errors'][] = $e->getMessage();
        }

        $this->addLog("=== КОНЕЦ syncProcedures: всего {$result['total']}, создано {$result['created']}, обновлено {$result['updated']} ===");

        return $result;
    }

    /**
     * Проверяет все товары каталога на нулевой остаток во всех складах.
     * При нуле устанавливает минимальный остаток (10 ед.) и уведомляет ответственного.
     */
    public function checkZeroStock(): void
    {
        $this->addLog('=== checkZeroStock: начало ===');

        if (!Loader::includeModule('catalog')) {
            $this->addLog('Модуль catalog не доступен');
            return;
        }

        $storeMap = $this->getStoreMap();
        if (empty($storeMap)) {
            $this->addLog('Маппинг складов пуст, проверка невозможна');
            return;
        }

        $zeroItems = [];
        $db        = \Bitrix\Main\Application::getConnection();

        foreach ($storeMap as $bitrixStoreId) {
            $bitrixStoreId = (int) $bitrixStoreId;
            if ($bitrixStoreId <= 0) {
                continue;
            }

            // Ищем товары где AMOUNT <= 0 ИЛИ b_catalog_product.QUANTITY <= 0.
            // StoreProductTable::update() не обновляет QUANTITY автоматически,
            // поэтому QUANTITY может быть отрицательным при ненулевом AMOUNT.
            $rows = $db->query(
                "SELECT sp.ID AS SP_ID, sp.PRODUCT_ID, sp.AMOUNT,
                        sp.QUANTITY_RESERVED, cp.QUANTITY AS CAT_QUANTITY
                 FROM b_catalog_store_product sp
                 INNER JOIN b_catalog_product cp ON cp.ID = sp.PRODUCT_ID
                 WHERE sp.STORE_ID = {$bitrixStoreId}
                   AND (sp.AMOUNT <= 0 OR cp.QUANTITY <= 0)"
            )->fetchAll();

            foreach ($rows as $row) {
                $productId = (int) $row['PRODUCT_ID'];
                if (isset($zeroItems[$productId])) {
                    continue;
                }

                StoreProductTable::update((int) $row['SP_ID'], ['AMOUNT' => 10, 'QUANTITY_RESERVED' => 0]);
                \CCatalogProduct::Update($productId, ['QUANTITY' => 10, 'QUANTITY_RESERVED' => 0]);
                $this->addLog(
                    "Остаток восстановлен: product={$productId}, store={$bitrixStoreId}"
                    . ", AMOUNT было={$row['AMOUNT']}, QUANTITY было={$row['CAT_QUANTITY']} → 10"
                );

                $zeroItems[$productId] = true;
            }
        }

        $count = count($zeroItems);
        $this->addLog("checkZeroStock: обработано позиций: {$count}");

        if ($count === 0) {
            return;
        }

        $responsibleId = (int) Option::get(self::MODULE_ID, 'MATRIX_RESPONSIBLE_USER_ID', '1') ?: 1;

        if (Loader::includeModule('im')) {
            \CIMNotify::Add([
                'TO_USER_ID'     => $responsibleId,
                'FROM_USER_ID'   => 1,
                'NOTIFY_TYPE'    => IM_NOTIFY_SYSTEM,
                'NOTIFY_MESSAGE' => "Matrix: обнаружено {$count} товаров с нулевым остатком. Остатки восстановлены до 10 ед.",
            ]);
        }

        $this->addLog('=== checkZeroStock: завершено ===');
    }
}
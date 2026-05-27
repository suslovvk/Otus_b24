<?php

namespace Matrix\Integration\Sync;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use CCrmDeal;
use Matrix\Integration\Api\MatrixClient;
use Matrix\Integration\Orm\ReceiptTable;

/**
 * Синхронизирует квитанции Matrix → Сделки Битрикс24.
 *
 * Маппинг стадий (по соотношению sum/paid):
 *   paid = 0           → PREPARATION  (Выставлен счёт)
 *   0 < paid < sum     → EXECUTING    (Частично оплачено)
 *   paid >= sum        → WON          (Оплачено)
 *
 * Позиции (items): ищем товар в каталоге по XML_ID MXPRODUCT_{id}.
 * Если найден → TYPE=1 (товар, PRODUCT_ID = ID элемента каталога).
 * Если не найден → TYPE=4 (услуга, PRODUCT_ID = 0) — без остатков, не отображается
 * как складской товар.
 *
 * @package Matrix\Integration\Sync
 */
class ReceiptSync
{
    private const MODULE_ID = 'matrix.integration';
    private const LOG_FILE  = '/bitrix/php_interface/log/matrix_integration.log';

    private const STAGE_INVOICE = 'PREPARATION';
    private const STAGE_PARTIAL = 'EXECUTING';
    private const STAGE_PAID    = 'WON';

    private const ROW_TYPE_PRODUCT = 1;
    private const ROW_TYPE_SERVICE = 4;

    private array $log = [];

    public function __construct(
        private readonly MatrixClient $client
    ) {}

    private function addLog(string $message): void
    {
        $this->log[] = $message;
        if (Option::get(self::MODULE_ID, 'MATRIX_DEBUG_MODE', 'N') === 'Y') {
            Debug::writeToFile(
                ['message' => $message, 'timestamp' => date('Y-m-d H:i:s')],
                '[ReceiptSync]',
                self::LOG_FILE
            );
        }
    }

    public function getLog(): array
    {
        return $this->log;
    }

    /**
     * Синхронизирует все квитанции пациента → Сделки CRM.
     *
     * @param int $matrixPatientId  ID пациента в Matrix
     * @param int $bitrixContactId  ID контакта в Битрикс24
     * @return array{total:int, created:int, updated:int, errors:list<string>}
     */
    public function syncForPatient(int $matrixPatientId, int $bitrixContactId): array
    {
        $this->addLog("syncForPatient: Matrix patient={$matrixPatientId}, contact={$bitrixContactId}");

        $result = ['total' => 0, 'created' => 0, 'updated' => 0, 'errors' => []];

        if (!Loader::includeModule('crm')) {
            $result['errors'][] = 'Модуль CRM не доступен';
            return $result;
        }

        try {
            foreach ($this->client->paginate("patients/{$matrixPatientId}/receipts") as $listItem) {
                $receiptId = (int) ($listItem['id'] ?? 0);
                if ($receiptId <= 0) {
                    continue;
                }

                // Детализация: items + checked_at (list содержит paid/datetime/type)
                $detail = [];
                try {
                    $detail = $this->client->get("patients/{$matrixPatientId}/receipts/{$receiptId}");
                } catch (\Throwable $e) {
                    $this->addLog("Детализация чека #{$receiptId}: " . $e->getMessage());
                }

                // list даёт paid/datetime/record_id, detail даёт items/checked_at/sum
                $data = array_merge($listItem, $detail, ['id' => $receiptId]);

                $action = $this->upsertReceipt($data, $matrixPatientId, $bitrixContactId);
                $result['total']++;
                match ($action) {
                    'created' => $result['created']++,
                    'updated' => $result['updated']++,
                    default   => null,
                };
            }
        } catch (\Throwable $e) {
            $this->addLog("Ошибка syncForPatient: " . $e->getMessage());
            $result['errors'][] = $e->getMessage();
        }

        $this->addLog(
            "syncForPatient done: total={$result['total']}, " .
            "created={$result['created']}, updated={$result['updated']}"
        );

        return $result;
    }

    /**
     * Создаёт или обновляет запись квитанции в ORM и связанную Сделку.
     *
     * @param array $data           Смёрженные данные list+detail из Matrix API
     * @param int   $matrixPatientId
     * @param int   $bitrixContactId
     * @return string 'created'|'updated'|'error'
     */
    public function upsertReceipt(array $data, int $matrixPatientId, int $bitrixContactId): string
    {
        $receiptId = (int) ($data['id'] ?? 0);
        if ($receiptId <= 0) {
            return 'error';
        }

        $sum   = (float) ($data['sum']  ?? 0);
        $paid  = (float) ($data['paid'] ?? 0);
        $type  = (int)   ($data['type'] ?? 0);
        $items = $data['items'] ?? [];
        $stage = $this->getStageId($sum, $paid);

        $checkedAt = $this->parseDate(
            $data['checked_at'] ?? $data['datetime'] ?? ''
        );

        $existing = ReceiptTable::getRow([
            'filter' => ['=MATRIX_RECEIPT_ID' => $receiptId],
            'select' => ['ID', 'BITRIX_DEAL_ID'],
        ]);

        $dealId = $existing ? (int) ($existing['BITRIX_DEAL_ID'] ?? 0) : 0;

        if ($dealId > 0) {
            $this->updateDeal($dealId, $sum, $stage, $items);
        } else {
            $dealId = $this->createDeal($receiptId, $type, $sum, $stage, $items, $bitrixContactId);
        }

        $fields = [
            'MATRIX_RECEIPT_ID' => $receiptId,
            'MATRIX_PATIENT_ID' => $matrixPatientId,
            'BITRIX_CONTACT_ID' => $bitrixContactId,
            'BITRIX_DEAL_ID'    => $dealId > 0 ? $dealId : null,
            'TYPE'              => $type ?: null,
            'SUM'               => $sum,
            'PAID'              => $paid,
            'CHECKED_AT'        => $checkedAt,
            'ITEMS'             => !empty($items) ? json_encode($items, JSON_UNESCAPED_UNICODE) : null,
        ];

        if ($existing) {
            ReceiptTable::update($existing['ID'], $fields);
            return 'updated';
        }

        ReceiptTable::add($fields);
        return 'created';
    }

    // ── Внутренние методы ────────────────────────────────────────────────────

    private function getStageId(float $sum, float $paid): string
    {
        if ($paid <= 0.0)         return self::STAGE_INVOICE;
        if ($paid < $sum - 0.01)  return self::STAGE_PARTIAL;
        return self::STAGE_PAID;
    }

    private function createDeal(
        int $receiptId,
        int $type,
        float $sum,
        string $stage,
        array $items,
        int $contactId
    ): int {
        $currency  = Option::get(self::MODULE_ID, 'MATRIX_BASE_CURRENCY', 'RUB') ?: 'RUB';
        $typeLabel = $type === 5 ? 'Товары' : 'Услуги';

        $dealFields = [
            'TITLE'       => "Квитанция Matrix #{$receiptId} ({$typeLabel})",
            'CONTACT_ID'  => $contactId,
            'OPPORTUNITY' => $sum,
            'CURRENCY_ID' => $currency,
            'STAGE_ID'    => $stage,
            'CLOSED'      => $stage === self::STAGE_PAID ? 'Y' : 'N',
        ];
        $deal   = new CCrmDeal();
        $dealId = (int) $deal->Add($dealFields);

        if ($dealId <= 0) {
            $this->addLog("Ошибка создания сделки для чека #{$receiptId}: " . ($deal->LAST_ERROR ?? ''));
            return 0;
        }

        if (!empty($items)) {
            $this->saveDealProductRows($dealId, $items);
        }

        $this->addLog("Сделка создана: ID={$dealId}, чек Matrix #{$receiptId}");
        return $dealId;
    }

    private function updateDeal(int $dealId, float $sum, string $stage, array $items): void
    {
        $currency = Option::get(self::MODULE_ID, 'MATRIX_BASE_CURRENCY', 'RUB') ?: 'RUB';

        $updateFields = [
            'OPPORTUNITY' => $sum,
            'CURRENCY_ID' => $currency,
            'STAGE_ID'    => $stage,
            'CLOSED'      => $stage === self::STAGE_PAID ? 'Y' : 'N',
        ];
        $deal = new CCrmDeal();
        $deal->Update($dealId, $updateFields);

        if (!empty($items)) {
            $this->saveDealProductRows($dealId, $items);
        }
    }

    /**
     * Сохраняет позиции квитанции как товарные строки сделки.
     * Товары из каталога (MXPRODUCT_{id}) → TYPE=1.
     * Процедуры/услуги → TYPE=4 (без остатков, не показываются как складской товар).
     */
    private function saveDealProductRows(int $dealId, array $items): void
    {
        $iblockId    = (int) Option::get(self::MODULE_ID, 'MATRIX_CATALOG_IBLOCK_ID', 0);
        $currency    = Option::get(self::MODULE_ID, 'MATRIX_BASE_CURRENCY', 'RUB') ?: 'RUB';
        $productRows = [];

        foreach ($items as $item) {
            $itemId   = (int)   ($item['id']     ?? 0);
            $name     = (string)($item['name']   ?? 'Услуга');
            $quantity = max(1.0, (float) ($item['amount'] ?? 1));
            $sumItem  = (float) ($item['sum']    ?? 0);
            $price    = $quantity > 0 ? round($sumItem / $quantity, 2) : 0.0;

            // Ищем элемент каталога товаров по XML_ID MXPRODUCT_{id}
            $productId = 0;
            $rowType   = self::ROW_TYPE_SERVICE;

            if ($iblockId > 0 && $itemId > 0) {
                $catalogId = $this->findCatalogProductId($iblockId, $itemId);
                if ($catalogId > 0) {
                    $productId = $catalogId;
                    $rowType   = self::ROW_TYPE_PRODUCT;
                }
            }

            $productRows[] = [
                'PRODUCT_ID'   => $productId,
                'PRODUCT_NAME' => $name,
                'PRICE'        => $price,
                'QUANTITY'     => $quantity,
                'SUM'          => $sumItem,
                'CURRENCY_ID'  => $currency,
                'TYPE'         => $rowType,
            ];
        }

        if (!empty($productRows)) {
            $rowsCopy = $productRows;
            CCrmDeal::SaveProductRows($dealId, $rowsCopy);
            $this->addLog("Позиции сохранены в сделку ID={$dealId}: " . count($productRows) . " шт.");
        }
    }

    /**
     * Ищет элемент каталога Битрикс по XML_ID = MXPRODUCT_{matrixItemId}.
     */
    private function findCatalogProductId(int $iblockId, int $matrixItemId): int
    {
        if (!Loader::includeModule('iblock')) {
            return 0;
        }

        $res = \CIBlockElement::GetList(
            [],
            ['=IBLOCK_ID' => $iblockId, '=XML_ID' => 'MXPRODUCT_' . $matrixItemId, 'ACTIVE' => 'Y'],
            false,
            ['nTopCount' => 1],
            ['ID']
        );

        $row = $res->Fetch();
        return $row ? (int) $row['ID'] : 0;
    }

    /**
     * Парсит дату из "d.m.Y H:i:s" (detail) или ISO-формата (list).
     */
    private function parseDate(string $str): ?DateTime
    {
        if ($str === '') {
            return null;
        }

        try {
            $php = preg_match('/^\d{2}\.\d{2}\.\d{4}/', $str)
                ? (\DateTime::createFromFormat('d.m.Y H:i:s', $str) ?: \DateTime::createFromFormat('d.m.Y', $str))
                : new \DateTime($str);

            return ($php instanceof \DateTime) ? DateTime::createFromPhp($php) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}

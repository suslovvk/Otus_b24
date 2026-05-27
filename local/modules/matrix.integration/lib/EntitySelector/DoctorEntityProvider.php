<?php

namespace Matrix\Integration\EntitySelector;

use Bitrix\Main\UserTable;
use Bitrix\UI\EntitySelector\BaseProvider;
use Bitrix\UI\EntitySelector\Dialog;
use Bitrix\UI\EntitySelector\Item;
use Bitrix\UI\EntitySelector\SearchQuery;

/**
 * EntitySelector-провайдер для выбора врача Matrix в диалоге Битрикс24.
 *
 * Сущность: matrix-doctor
 * ID элемента: UF_MATRIX_DOCTOR_ID (числовой ID врача в Matrix CRM)
 */
class DoctorEntityProvider extends BaseProvider
{
    /** @var string ID типа сущности в EntitySelector */
    public const ENTITY_ID = 'matrix-doctor';

    /**
     * Провайдер всегда доступен.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Возвращает элементы по списку Matrix Doctor ID.
     *
     * @param array $ids Массив Matrix Doctor ID (UF_MATRIX_DOCTOR_ID)
     * @return Item[]
     */
    public function getItems(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $rows = UserTable::getList([
            'filter' => [
                '@UF_MATRIX_DOCTOR_ID' => $ids,
                '=ACTIVE'              => 'Y',
            ],
            'select' => ['NAME', 'LAST_NAME', 'UF_MATRIX_DOCTOR_ID'],
        ])->fetchAll();

        return $this->buildItemsFromRows($rows);
    }

    /**
     * Возвращает уже выбранные элементы (делегирует в getItems).
     *
     * @param array $ids Массив Matrix Doctor ID
     * @return Item[]
     */
    public function getSelectedItems(array $ids): array
    {
        return $this->getItems($ids);
    }

    /**
     * Выполняет поиск врачей и добавляет результаты в диалог.
     *
     * Если строка поиска не пустая — фильтрует по LAST_NAME или NAME.
     * Если пустая — возвращает всех активных врачей с лимитом 100.
     *
     * @param SearchQuery $searchQuery Объект поискового запроса
     * @param Dialog      $dialog      Диалог EntitySelector
     * @return void
     */
    public function doSearch(SearchQuery $searchQuery, Dialog $dialog): void
    {
        $query = trim($searchQuery->getQuery());

        if ($query !== '') {
            $filter = [
                'LOGIC'   => 'OR',
                '%LAST_NAME' => $query,
                '%NAME'      => $query,
            ];
        } else {
            $filter = [];
        }

        $filter['=ACTIVE']              = 'Y';
        $filter['!=UF_MATRIX_DOCTOR_ID'] = false;

        $rows = UserTable::getList([
            'filter' => $filter,
            'select' => ['NAME', 'LAST_NAME', 'UF_MATRIX_DOCTOR_ID'],
            'limit'  => 100,
            'order'  => ['LAST_NAME' => 'ASC'],
        ])->fetchAll();

        $dialog->addItems($this->buildItemsFromRows($rows));
    }

    /**
     * Обработчик события onEntitySelectorDialogInitialize.
     *
     * Добавляет провайдер в диалог, если среди запрошенных сущностей
     * присутствует entityTypeId === ENTITY_ID.
     *
     * @param \Bitrix\Main\Event $event
     * @return void
     */
    public static function onDialogInitialize(\Bitrix\Main\Event $event): void
    {
        /** @var Dialog $dialog */
        $dialog = $event->getParameter('dialog');
        if (!($dialog instanceof Dialog)) {
            return;
        }

        foreach ($dialog->getEntities() as $entity) {
            if ($entity->getEntityTypeId() === self::ENTITY_ID) {
                $dialog->addProvider(new static());
                return;
            }
        }
    }

    /**
     * Строит массив Item из строк UserTable.
     *
     * @param array $rows
     * @return Item[]
     */
    private function buildItemsFromRows(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $matrixDoctorId = (int) ($row['UF_MATRIX_DOCTOR_ID'] ?? 0);
            if ($matrixDoctorId <= 0) {
                continue;
            }

            $title = trim(($row['LAST_NAME'] ?? '') . ' ' . ($row['NAME'] ?? ''));
            if ($title === '') {
                $title = 'Врач #' . $matrixDoctorId;
            }

            $items[] = new Item([
                'entityId' => self::ENTITY_ID,
                'id'       => $matrixDoctorId,
                'title'    => $title,
            ]);
        }

        return $items;
    }
}

<?php
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\PageNavigation;
use Bitrix\Main\Grid\Options as GridOptions;
use Bitrix\Main\Loader;
use Otus\Crm\Prices\Orm\ServicesTable;

Loader::includeModule('otus.crm.prices');
Loc::loadMessages(__FILE__);

/**
 * Компонент для отображения списка цен в GRID
 *
 * Class PricesListComponent
 */
class PricesListComponent extends \CBitrixComponent implements Controllerable
{
    /**
     * Конфигурация действий компонента
     *
     * @return array
     */
    public function configureActions(): array
    {
        return [];
    }

    /**
     * Возвращает заголовки колонок GRID
     *
     * @return array
     */
    private function getHeaders(): array
    {
        return [   
            [
                'id' => 'ID',
                'name' => 'ID',
                'sort' => 'ID',
                'default' => true,
            ],
            [
                'id' => 'NAME',
                'name' => Loc::getMessage('OTUS_PRICES_GRID_NAME'),
                'sort' => 'NAME',
                'default' => true,
            ],
            [
                'id' => 'PRICE',
                'name' => Loc::getMessage('OTUS_PRICES_GRID_PRICE'),
                'sort' => 'PRICE',
                'default' => true,
            ],
            [
                'id' => 'ACTIVE',
                'name' => Loc::getMessage('OTUS_PRICES_GRID_ACTIVE'),
                'sort' => 'ACTIVE',
                'default' => true,
            ],
        ];
    }

    /**
     * Главный метод выполнения компонента
     *
     * @return void
     */
    public function executeComponent(): void
    {
        $this->prepareGridData();
        $this->includeComponentTemplate();
    }

    /**
     * Подготовка данных для GRID
     *
     * @return void
     */
    private function prepareGridData(): void
    {
        $this->arResult['HEADERS'] = $this->getHeaders();
        $this->arResult['FILTER_ID'] = 'PRICES_GRID';

        $gridOptions = new GridOptions($this->arResult['FILTER_ID']);
        $navParams = $gridOptions->getNavParams();

        // Настройка пагинации
        $nav = new PageNavigation($this->arResult['FILTER_ID']);
        $nav->allowAllRecords(true)
            ->setPageSize($navParams['nPageSize'])
            ->initFromUri();

        // Получение сортировки
        $sort = $gridOptions->getSorting([
            'sort' => [
                'ID' => 'DESC',
            ],
            'vars' => [
                'by' => 'by',
                'order' => 'order',
            ],
        ]);

        // Подсчет общего количества записей
        $countQuery = ServicesTable::query()
            ->setSelect(['ID']);
        
        $nav->setRecordCount($countQuery->queryCountTotal());

        // Получение данных
        $servicesQuery = ServicesTable::query()
            ->setSelect(['ID', 'NAME', 'PRICE', 'ACTIVE'])
            ->setLimit($nav->getLimit())
            ->setOffset($nav->getOffset())
            ->setOrder($sort['sort']);

        $services = $servicesQuery->exec();

        $this->arResult['GRID_LIST'] = $this->prepareGridList($services);
        $this->arResult['NAV'] = $nav;
    }

    /**
     * Подготовка списка элементов для GRID
     *
     * @param \Bitrix\Main\ORM\Query\Result $services
     * @return array
     */
    private function prepareGridList(\Bitrix\Main\ORM\Query\Result $services): array
    {
        $gridList = [];

        while ($service = $services->fetch()) {
            $gridList[] = [
                'data' => [
                    'ID' => $service['ID'],
                    'NAME' => $service['NAME'],
                    'PRICE' => $service['PRICE'],
                    'ACTIVE' => $service['ACTIVE'] === 'Y' ? 'Да' : 'Нет',
                ],
                'actions' => [],
            ];
        }

        return $gridList;
    }
}
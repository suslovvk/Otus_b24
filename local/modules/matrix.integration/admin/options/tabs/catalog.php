<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

$storeMap    = json_decode(Option::get('matrix.integration', 'MATRIX_STORE_MAP', '{}'), true) ?: [];
$iblockId    = (int) Option::get('matrix.integration', 'MATRIX_CATALOG_IBLOCK_ID', 0);

// Получаем список складов Битрикс24
$bitrixStores = [];
if (Loader::includeModule('catalog')) {
    $storeResult = \Bitrix\Catalog\StoreTable::getList([
        'select' => ['ID', 'TITLE'],
        'filter' => ['ACTIVE' => 'Y'],
        'order'  => ['SORT' => 'ASC'],
    ]);
    while ($store = $storeResult->fetch()) {
        $bitrixStores[$store['ID']] = $store['TITLE'];
    }
}

// Получаем список инфоблоков типа catalog
$iblocks = [];
if (Loader::includeModule('iblock')) {
    $iblockResult = \CIBlock::GetList(['SORT' => 'ASC'], ['ACTIVE' => 'Y']);
    while ($iblock = $iblockResult->Fetch()) {
        $iblocks[$iblock['ID']] = '[' . $iblock['ID'] . '] ' . $iblock['NAME'];
    }
}
?>

<tr class="heading">
    <td colspan="2">Инфоблок каталога</td>
</tr>

<tr>
    <td width="40%"><label for="MATRIX_CATALOG_IBLOCK_ID">Инфоблок товаров:</label></td>
    <td width="60%">
        <select id="MATRIX_CATALOG_IBLOCK_ID" name="MATRIX_CATALOG_IBLOCK_ID">
            <option value="0">— Выберите инфоблок —</option>
            <?php foreach ($iblocks as $id => $name): ?>
                <option value="<?= $id ?>" <?= $iblockId === $id ? 'selected' : '' ?>>
                    <?= htmlspecialcharsbx($name) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <br><small>Инфоблок в котором будут храниться товары из Matrix CRM</small>
    </td>
</tr>

<tr class="heading">
    <td colspan="2">Маппинг складов</td>
</tr>

<tr>
    <td colspan="2">
        <small>Сопоставьте склады Matrix CRM со складами Битрикс24.
            Список складов Matrix загружается из API после сохранения настроек подключения.</small>
    </td>
</tr>

<tr>
    <td colspan="2">
        <table id="matrix-store-map-table" style="width:100%; border-collapse: collapse;">
            <thead>
            <tr style="background:#f5f5f5;">
                <th style="padding:6px 10px; text-align:left; border:1px solid #ddd;">Склад Matrix CRM</th>
                <th style="padding:6px 10px; text-align:left; border:1px solid #ddd;">Склад Битрикс24</th>
                <th style="padding:6px 10px; width:40px; border:1px solid #ddd;"></th>
            </tr>
            </thead>
            <tbody id="matrix-store-map-body">
            <?php foreach ($storeMap as $matrixStoreId => $bitrixStoreId): ?>
                <tr class="matrix-store-map-row">
                    <td style="padding:4px 6px; border:1px solid #ddd;">
                        <input type="text"
                               name="MATRIX_STORE_MAP_MATRIX[]"
                               value="<?= (int) $matrixStoreId ?>"
                               size="20"
                               placeholder="ID склада Matrix"/>
                    </td>
                    <td style="padding:4px 6px; border:1px solid #ddd;">
                        <select name="MATRIX_STORE_MAP_BITRIX[]">
                            <option value="0">— Выберите склад —</option>
                            <?php foreach ($bitrixStores as $storeId => $storeTitle): ?>
                                <option value="<?= $storeId ?>"
                                    <?= (int) $bitrixStoreId === (int) $storeId ? 'selected' : '' ?>>
                                    <?= htmlspecialcharsbx($storeTitle) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td style="padding:4px 6px; border:1px solid #ddd; text-align:center;">
                        <button type="button"
                                class="adm-btn matrix-store-map-remove"
                                style="color:red;">✕</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top:8px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <button type="button" class="adm-btn" id="matrix-store-map-add">+ Добавить строку</button>
            <button type="button" class="adm-btn" id="matrix-load-stores">↺ Загрузить склады из Matrix</button>
            <span id="matrix-load-stores-result" style="font-size:12px;"></span>
        </div>

        <script id="matrix-store-map-row-tpl" type="text/html">
            <tr class="matrix-store-map-row">
                <td style="padding:4px 6px; border:1px solid #ddd;">
                    <input type="text" name="MATRIX_STORE_MAP_MATRIX[]" value="" size="20"
                           placeholder="ID склада Matrix"/>
                </td>
                <td style="padding:4px 6px; border:1px solid #ddd;">
                    <select name="MATRIX_STORE_MAP_BITRIX[]">
                        <option value="0">— Выберите склад —</option>
                        <?php foreach ($bitrixStores as $storeId => $storeTitle): ?>
                            <option value="<?= $storeId ?>">
                                <?= htmlspecialcharsbx($storeTitle) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td style="padding:4px 6px; border:1px solid #ddd; text-align:center;">
                    <button type="button" class="adm-btn matrix-store-map-remove"
                            style="color:red;">✕</button>
                </td>
            </tr>
        </script>
    </td>
</tr>

<tr class="heading">
    <td colspan="2">Синхронизация</td>
</tr>

<tr>
    <td colspan="2">
        <button type="button" class="adm-btn" id="matrix-sync-catalog">
            Синхронизировать каталог
        </button>
        <span id="matrix-sync-catalog-result" style="margin-left:10px;"></span>
        <br><small style="margin-top:4px; display:block;">
            Запускает синхронизацию групп товаров, цен и остатков по складам.
        </small>
    </td>
</tr>
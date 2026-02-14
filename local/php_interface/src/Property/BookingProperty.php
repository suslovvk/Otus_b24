<? 
namespace Otus\Property;

class BookingProperty {
    public static function GetUserTypeDescription()
    {
        return array (     

        'PROPERTY_TYPE' => 'S', // Выбрал строку тк мы все равно не храним ничего в свойстве
        'USER_TYPE' => 'booking_property',    
        'DESCRIPTION' => 'Расписание специалиста',
        
        // Методы отображения
    //    'GetAdminListViewHTML' => [__CLASS__, 'GetAdminListViewHTML'],  // ДЛЯ СПИСКА (наш случай!)
        'GetPropertyFieldHtml' => [__CLASS__, 'GetPropertyFieldHtml'],  // для формы редактирования
        'GetPublicViewHTML' => [__CLASS__, 'GetPublicViewHTML'],
        
        );
    }
    // public static function GetAdminListViewHTML($arProperty, $value, $strHTMLControlName)
    // {
    //      return '<span style="color: red; font-weight: bold;">TEST BOOKING</span>';
   
    // }
    public static function GetPropertyFieldHtml($arProperty, $value, $strHTMLControlName)
    {
        // Для формы редактирования элемента
        // Пока просто вернем пустую строку, т.к. свойство не редактируется
        return '';
    }
    // Для публичного просмотра (модуль Списки)
    public static function GetPublicViewHTML($arProperty, $value, $strHTMLControlName)
    {
        \Bitrix\Main\Loader::includeModule('iblock');
        
        $elementId = $arProperty['ELEMENT_ID'];
        $iblockId = $arProperty['IBLOCK_ID'];
        
        // Получаем свойство PROCEDURES текущего элемента (врача)
        $procedureIds = [];
        $rsProperties = \CIBlockElement::GetProperty($iblockId, $elementId, [], ['CODE' => 'PROCEDURES']);
        while ($arProp = $rsProperties->Fetch()) {
            if (!empty($arProp['VALUE'])) {
                $procedureIds[] = $arProp['VALUE'];
            }
        }
        
        // Если нет процедур - ничего не выводим
        if (empty($procedureIds)) {
            return '';
        }
        
        // Получаем названия процедур по их ID
        $procedures = [];
        $rsElements = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => 20, 'ID' => $procedureIds],
            false,
            false,
            ['ID', 'NAME']
        );
        while ($arElement = $rsElements->Fetch()) {
            $procedures[$arElement['ID']] = $arElement['NAME'];
        }
        
        // Подключаем JS файл
        \Bitrix\Main\UI\Extension::load('ui.dialogs.messagebox'); // для попапов Битрикс
        \Bitrix\Main\Page\Asset::getInstance()->addJs('/local/php_interface/src/assets/js/booking-property.js');
        
        // Формируем HTML с процедурами
        $html = '<div class="booking-procedures">';
        foreach ($procedures as $procedureId => $procedureName) {
            $html .= sprintf(
                '<a class="booking-procedure-item" data-procedure-id="%d" data-procedure-name="%s" data-doctor-id="%d" >%s</a>,</br>',
                $procedureId,
                htmlspecialchars($procedureName),
                $elementId,
                htmlspecialchars($procedureName)
            );
        }
        $html .= '</div>';
        
        return $html;
    }

  


}
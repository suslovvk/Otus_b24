<?php

namespace Otus\Dz11;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__DIR__ . '/../install_field.php');

/**
 * Устанавливает пользовательское поле «Дата последней коммуникации» в карточке контакта CRM.
 */
class FieldInstaller
{
    /** Идентификатор сущности CRM-контакта для пользовательских полей */
    private const ENTITY_ID = 'CRM_CONTACT';

    /** Имя создаваемого пользовательского поля */
    private const FIELD_NAME = 'UF_CRM_LAST_COMMUNICATION';

    /**
     * Проверяет, существует ли поле UF_CRM_LAST_COMMUNICATION.
     *
     * @return bool
     */
    public function isFieldExists(): bool
    {
        $dbField = \CUserTypeEntity::GetList(
            [],
            [
                'ENTITY_ID'  => self::ENTITY_ID,
                'FIELD_NAME' => self::FIELD_NAME,
            ]
        );

        return (bool)$dbField->Fetch();
    }

    /**
     * Создаёт пользовательское поле типа «datetime» в карточке CRM-контакта.
     *
     * @return array{success: bool, message: string}
     */
    public function install(): array
    {
        if ($this->isFieldExists()) {
            return [
                'success' => false,
                'message' => Loc::getMessage('DZ11_FIELD_ALREADY_EXISTS'),
            ];
        }

        $fieldId = $this->createField();

        if ($fieldId === false) {
            global $APPLICATION;
            $error = $APPLICATION->GetException();

            return [
                'success' => false,
                'message' => Loc::getMessage('DZ11_FIELD_ERROR') . ': ' . ($error ? $error->GetString() : ''),
            ];
        }

        return [
            'success' => true,
            'message' => Loc::getMessage('DZ11_FIELD_SUCCESS', ['#ID#' => $fieldId]),
        ];
    }

    /**
     * Выполняет создание поля через API Битрикс и возвращает его ID.
     *
     * @return int|false ID созданного поля или false при ошибке
     */
    private function createField(): int|false
    {
        $label = Loc::getMessage('DZ11_FIELD_LABEL');

        $oUserType = new \CUserTypeEntity();

        return $oUserType->Add([
            'ENTITY_ID'          => self::ENTITY_ID,
            'FIELD_NAME'         => self::FIELD_NAME,
            'USER_TYPE_ID'       => 'datetime',
            'SORT'               => 100,
            'MULTIPLE'           => 'N',
            'MANDATORY'          => 'N',
            'SHOW_FILTER'        => 'N',
            'SHOW_IN_LIST'       => 'Y',
            'EDIT_IN_LIST'       => 'Y',
            'IS_SEARCHABLE'      => 'N',
            'SETTINGS'           => ['DEFAULT_VALUE' => ''],
            'EDIT_FORM_LABEL'    => ['ru' => $label],
            'LIST_COLUMN_LABEL'  => ['ru' => $label],
            'LIST_FILTER_LABEL'  => ['ru' => $label],
            'ERROR_MESSAGE'      => ['ru' => ''],
            'HELP_MESSAGE'       => ['ru' => ''],
        ]);
    }
}

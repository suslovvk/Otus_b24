<?php

use Bitrix\Main\Loader;
use Otus\Dadata\DadataClient;

/**
 * Активити бизнес-процесса: поиск или создание компании CRM по ИНН через DaData.
 *
 * Входной параметр:  INN       — ИНН компании (строка)
 * Выходной параметр: CompanyId — ID компании в CRM (int, 0 при ошибке)
 */
class CBPDadataGetCompanyActivity extends CBPActivity
{
    private const CRM_COMPANY_INN_FIELD = 'UF_CRM_INN';

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        parent::__construct($name);

        $this->arProperties = [
            'Title'              => '',
            'INN'                => null,
            'CompanyId'          => null,
            'CompanyIdFormatted' => null,
        ];

        $this->SetPropertiesTypes([
            'CompanyId'          => ['Type' => 'int'],
            'CompanyIdFormatted' => ['Type' => 'string'],
        ]);
    }

    /**
     * Точка входа бизнес-процесса.
     * Ищет компанию по ИНН; при отсутствии создаёт через DaData.
     *
     * @return int
     */
    public function Execute()
    {
        $inn = trim((string) $this->INN);

        if ($inn === '') {
            $this->WriteToTrackingService('DadataGetCompanyActivity: ИНН не задан');
            return CBPActivityExecutionStatus::Closed;
        }

        if (!Loader::includeModule('crm')) {
            $this->WriteToTrackingService('DadataGetCompanyActivity: модуль crm не подключён');
            return CBPActivityExecutionStatus::Closed;
        }

        $companyId = $this->findCompanyByInn($inn);

        if ($companyId <= 0) {
            $companyId = $this->createCompanyFromDadata($inn);
        }

        $this->CompanyId          = $companyId;
        $this->CompanyIdFormatted = $companyId > 0 ? 'CO_' . $companyId : '';

        $this->WriteToTrackingService(
            $companyId > 0
                ? 'DadataGetCompanyActivity: ID компании = ' . $companyId
                : 'DadataGetCompanyActivity: компанию создать не удалось'
        );

        return CBPActivityExecutionStatus::Closed;
    }

    /**
     * Ищет компанию CRM по значению UF-поля ИНН.
     *
     * @param string $inn
     *
     * @return int ID компании или 0
     */
    private function findCompanyByInn(string $inn): int
    {
        $arOrder  = ['ID' => 'ASC'];
        $arFilter = [self::CRM_COMPANY_INN_FIELD => $inn];

        $result = CCrmCompany::GetList($arOrder, $arFilter, ['ID'], 1);

        if ($row = $result->Fetch()) {
            return (int) $row['ID'];
        }

        return 0;
    }

    /**
     * Запрашивает данные компании из DaData и создаёт её в CRM.
     *
     * @param string $inn
     *
     * @return int ID созданной компании или 0 при ошибке
     */
    private function createCompanyFromDadata(string $inn): int
    {
        $client = new DadataClient();
        $data   = $client->getCompanyByInn($inn);

        if ($data === null) {
            $this->WriteToTrackingService(
                'DadataGetCompanyActivity: DaData не вернула данные для ИНН ' . $inn
            );
            return 0;
        }

        $arFields = [
            'TITLE'                     => $data['shortName'],
            'COMPANY_TYPE'              => 'CUSTOMER',
            'OPENED'                    => 'Y',
            self::CRM_COMPANY_INN_FIELD => $inn,
            'ADDRESS'                   => $data['address'],
        ];

        $company   = new CCrmCompany(false);
        $companyId = (int) $company->Add($arFields);

        if ($companyId <= 0) {
            $this->WriteToTrackingService(
                'DadataGetCompanyActivity: ошибка создания компании — ' . ($company->LAST_ERROR ?? 'неизвестно')
            );
        }

        return $companyId;
    }

    /**
     * Рендерит форму настройки активити в конструкторе бизнес-процессов.
     *
     * @param array       $documentType
     * @param string      $activityName
     * @param array       $arWorkflowTemplate
     * @param array       $arWorkflowParameters
     * @param array       $arWorkflowVariables
     * @param array|null  $arCurrentValues
     * @param string      $formName
     *
     * @return string
     */
    public static function GetPropertiesDialog(
        $documentType,
        $activityName,
        $arWorkflowTemplate,
        $arWorkflowParameters,
        $arWorkflowVariables,
        $arCurrentValues = null,
        $formName = ''
    ) {
        unset($documentType, $arWorkflowParameters, $arWorkflowVariables);

        $runtime = CBPRuntime::GetRuntime();

        if (!is_array($arCurrentValues)) {
            $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
            if (is_array($arCurrentActivity['Properties'] ?? null)) {
                $arCurrentValues['inn'] = $arCurrentActivity['Properties']['INN'] ?? '';
            }
        }

        return $runtime->ExecuteResourceFile(
            __FILE__,
            'properties_dialog.php',
            [
                'arCurrentValues' => $arCurrentValues,
                'formName'        => $formName,
            ]
        );
    }

    /**
     * Описание полей активити — используется роботами Битрикс24.
     *
     * @param array $documentType
     * @param array $context
     *
     * @return array
     */
    public static function getPropertiesMap(array $documentType, array $context = []): array
    {
        return [
            'INN' => [
                'Name'      => 'ИНН компании',
                'FieldName' => 'inn',
                'Type'      => 'string',
                'Required'  => 'Y',
            ],
        ];
    }

    /**
     * Сохраняет значения формы настройки активити в шаблон бизнес-процесса.
     *
     * @param array  $documentType
     * @param string $activityName
     * @param array  $arWorkflowTemplate
     * @param array  $arWorkflowParameters
     * @param array  $arWorkflowVariables
     * @param array  $arCurrentValues
     * @param array  $arErrors
     *
     * @return bool
     */
    public static function GetPropertiesDialogValues(
        $documentType,
        $activityName,
        &$arWorkflowTemplate,
        &$arWorkflowParameters,
        &$arWorkflowVariables,
        $arCurrentValues,
        &$arErrors
    ) {
        unset($documentType, $arWorkflowParameters, $arWorkflowVariables);

        $arErrors = [];

        $inn = trim($arCurrentValues['inn'] ?? '');

        if ($inn === '') {
            $arErrors[] = ['code' => 'INN', 'message' => 'Укажите ИНН компании'];
            return false;
        }

        $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
        $arCurrentActivity['Properties'] = ['INN' => $inn];

        return true;
    }
}

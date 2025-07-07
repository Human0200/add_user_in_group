<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

class CBPAddUserToGroupActivity extends CBPActivity
{
    public function __construct($name)
    {
        parent::__construct($name);
        $this->arProperties = array(
            'Title' => '',
            'UserIDs' => [], // Массив пользователей
            'GroupID' => null,
            'Moderators' => [],
            'Owner' => null,

            // return
            'Result' => false
        );

        $this->SetPropertiesTypes(array(
            'Result' => array(
                'Type' => 'bool'
            )
        ));
    }

    protected function ReInitialize()
    {
        parent::ReInitialize();
        $this->Result = false;
    }

    public function Execute()
    {
        if (!CModule::IncludeModule('socialnetwork'))
        {
            return CBPActivityExecutionStatus::Closed;
        }
    
        $userIds = $this->UserIDs;
        $groupId = $this->GroupID;
        $moderators = $this->Moderators;
        $owner = $this->Owner;
    
        if (empty($userIds) || !$groupId)
        {
            $this->WriteToTrackingService('Не указаны пользователи или группа');
            return CBPActivityExecutionStatus::Closed;
        }
    
        $success = true;

        // Добавление модераторов
        foreach ($moderators as $moderatorId) {
            $cleanModeratorId = str_replace('user_', '', $moderatorId);
            $this->WriteToTrackingService('Добавляю модератора к группе: ' . $cleanModeratorId);
            $arFields = array(
                "USER_ID" => $cleanModeratorId,
                "GROUP_ID" => $groupId,
                "ROLE" => SONET_ROLES_MODERATOR,
                "INITIATED_BY_TYPE" => SONET_INITIATED_BY_GROUP,
                "INITIATED_BY_USER_ID" => $cleanModeratorId
            );
    
            $relationId = CSocNetUserToGroup::Add($arFields);
            if (!$relationId) {
                $success = false;
                $this->WriteToTrackingService(GetMessage('BPAG_ADD_USER_ERROR') . " UserID: $cleanModeratorId");
            }
        }
    
        // Добавление обычных пользователей
        foreach ($userIds as $userId) {
            $cleanUserId = str_replace('user_', '', $userId);
            if ($cleanUserId == $owner || in_array($cleanUserId, $moderators)) {
                continue; // Пропускаем, если пользователь уже добавлен как владелец или модератор
            }
            $this->WriteToTrackingService('Добавляю пользователя к группе: ' . $cleanUserId);
            $arFields = array(
                "USER_ID" => $cleanUserId,
                "GROUP_ID" => $groupId,
                "ROLE" => SONET_ROLES_USER,
                "INITIATED_BY_TYPE" => SONET_INITIATED_BY_GROUP,
                "INITIATED_BY_USER_ID" => $cleanUserId
            );
    
            $relationId = CSocNetUserToGroup::Add($arFields);
            if (!$relationId) {
                $success = false;
                $this->WriteToTrackingService(GetMessage('BPAG_ADD_USER_ERROR') . " UserID: $cleanUserId");
            }
        }

// Добавление владельца
if ($owner) {
    $this->WriteToTrackingService('Начинаю процесс изменения владельца группы.');

    // Очищаем ID нового владельца от префикса "user_"
    $newOwnerId = str_replace('user_', '', $owner);

    // Проверяем, существует ли новый владелец
    $rsUser = CUser::GetByID($newOwnerId);
    if (!$rsUser->Fetch()) {
        $this->WriteToTrackingService('Ошибка: Пользователь с ID ' . $newOwnerId . ' не найден.');
        $success = false;
        return;
    }

    // Получаем текущего владельца группы
    $currentOwnerId = false;
    $dbRelation = CSocNetUserToGroup::GetList(
        array(),
        array(
            "GROUP_ID" => $groupId,
            "ROLE" => SONET_ROLES_OWNER
        ),
        false,
        false,
        array("USER_ID")
    );

    if ($arRelation = $dbRelation->Fetch()) {
        $currentOwnerId = $arRelation["USER_ID"];
    }

    // Добавляем нового владельца в группу
    $arFields = array(
        "USER_ID" => $newOwnerId,
        "GROUP_ID" => $groupId,
        "ROLE" => SONET_ROLES_OWNER,
        "INITIATED_BY_TYPE" => SONET_INITIATED_BY_GROUP,
        "INITIATED_BY_USER_ID" => $currentOwnerId
    );

    $relationId = CSocNetUserToGroup::Add($arFields);

    if (!$relationId) {
        $success = false;
        $this->WriteToTrackingService(GetMessage('BPAG_ADD_USER_ERROR') . " UserID: $newOwnerId");
        return;
    }

    // Обновляем владельца группы
    $arFieldsUpdate = array(
        "OWNER_ID" => $newOwnerId // Устанавливаем нового владельца
    );

    $result = CSocNetGroup::Update($groupId, $arFieldsUpdate);

    // Проверяем результат обновления
    if ($result) {
        $this->WriteToTrackingService('Владелец группы успешно изменен на пользователя с ID ' . $newOwnerId);

        // Если был текущий владелец, удаляем его связь с группой
        if ($currentOwnerId) {
            $deleteResult = CSocNetUserToGroup::Delete($currentOwnerId, $groupId);
            if ($deleteResult) {
                $this->WriteToTrackingService('Старая связь владельца (ID ' . $currentOwnerId . ') успешно удалена.');
            } else {
                $this->WriteToTrackingService('Ошибка при удалении старой связи владельца.');
            }
        }
    } else {
        $success = false;
        $errorMessage = $GLOBALS['APPLICATION']->GetException()->GetString();
        $this->WriteToTrackingService('Ошибка при изменении владельца группы: ' . $errorMessage);
    }
}
    
        $this->Result = $success;
        if ($success) {
            $this->WriteToTrackingService(GetMessage('BPAG_USERS_ADDED_TO_GROUP'), 0, CBPTrackingType::Report);
        }
    
        return CBPActivityExecutionStatus::Closed;
    }

    public static function ValidateProperties($arTestProperties = array(), CBPWorkflowTemplateUser $user = null)
    {
        $arErrors = array();

        if (!array_key_exists("UserIDs", $arTestProperties) || empty($arTestProperties["UserIDs"]))
            $arErrors[] = array("code" => "NotExist", "parameter" => "UserIDs", "message" => GetMessage('BPAG_USER_IDS_REQUIRED'));

        if (!array_key_exists("GroupID", $arTestProperties) || empty($arTestProperties["GroupID"]))
            $arErrors[] = array("code" => "NotExist", "parameter" => "GroupID", "message" => GetMessage('BPAG_GROUP_ID_REQUIRED'));

        return array_merge($arErrors, parent::ValidateProperties($arTestProperties, $user));
    }

    public static function GetPropertiesDialog($documentType, $activityName, $arWorkflowTemplate, $arWorkflowParameters, $arWorkflowVariables, $arCurrentValues = null, $formName = "", $popupWindow = null)
    {
        $runtime = CBPRuntime::GetRuntime();
        $documentService = $runtime->GetService("DocumentService");

        if (!is_array($arWorkflowParameters))
            $arWorkflowParameters = array();
        if (!is_array($arWorkflowVariables))
            $arWorkflowVariables = array();

        $arMap = array(
            'UserIDs' => 'user_ids',
            'GroupID' => 'group_id',
            'Moderators' => 'moderators',
            'Owner' => 'owner'
        );

        if (!is_array($arCurrentValues))
        {
            $arCurrentValues = array();
            $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
            if (is_array($arCurrentActivity["Properties"]))
            {
                foreach ($arMap as $k => $v)
                {
                    if (array_key_exists($k, $arCurrentActivity["Properties"]))
                    {
                        $arCurrentValues[$arMap[$k]] = $arCurrentActivity["Properties"][$k];
                    }
                    else
                    {
                        $arCurrentValues[$arMap[$k]] = "";
                    }
                }
            }
            else
            {
                foreach ($arMap as $k => $v)
                    $arCurrentValues[$arMap[$k]] = "";
            }
        }

        $arFieldTypes = $documentService->GetDocumentFieldTypes($documentType);
        $arDocumentFields = $documentService->GetDocumentFields($documentType);

        return $runtime->ExecuteResourceFile(
            __FILE__,
            "properties_dialog.php",
            array(
                "arCurrentValues" => $arCurrentValues,
                "arDocumentFields" => $arDocumentFields,
                "arFieldTypes" => $arFieldTypes,
                "javascriptFunctions" => null,
                "formName" => $formName,
                "popupWindow" => &$popupWindow,
            )
        );
    }

    public static function GetPropertiesDialogValues($documentType, $activityName, &$arWorkflowTemplate, &$arWorkflowParameters, &$arWorkflowVariables, $arCurrentValues, &$arErrors)
    {
        $arErrors = array();
        $runtime = CBPRuntime::GetRuntime();
        $arMap = array(
            'UserIDs' => 'user_ids',
            'GroupID' => 'group_id',
            'Moderators' => 'moderators',
            'Owner' => 'owner'
        );
        $arProperties = array();

        foreach ($arMap as $key => $value)
        {
            $arProperties[$key] = $arCurrentValues[$value];
        }

        $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
        $arCurrentActivity["Properties"] = $arProperties;

        return true;
    }
}
?>
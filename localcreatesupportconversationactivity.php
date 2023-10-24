<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Im\Color;
use Bitrix\Im\V2\Chat;
use Bitrix\Bizproc\FieldType;
use Bitrix\Main\ErrorCollection;
use Bitrix\Main\Localization\Loc;
use Bitrix\Im\V2\Chat\ChatFactory;
use Bitrix\ImOpenLines\Chat as OpenLinesChat;
use Bitrix\Bizproc\Activity\PropertiesDialog;

class CBPLocalCreateSupportConversationActivity extends CBPActivity
{
    /** @var int CONNECTOR_ID */
    protected const CONNECTOR_ID = "livechat";

    /** @var int LINE_ID */
    protected const LINE_ID = 74;

    /** @var ChatFactory $chatFactory */
    protected ChatFactory $chatFactory;

    /** @var ErrorCollection $errorCollection */
    protected ErrorCollection $errorCollection;

    /**
     * Initialize activity
     * 
     * @param string $name
     */
    public function __construct($name)
    {
        parent::__construct($name);

        if (!$this->loadModules()) {
            return CBPActivityExecutionStatus::Closed;
        }

        $this->chatFactory = ChatFactory::getInstance();
        $this->errorCollection = new ErrorCollection();
        $this->arProperties = [
            "Title" => "",
            "ConversationTitle" => null,
            "AutoMessage" => null,
            "SupportUser" => null,
            "Employee" => null,
        ];
    }
    /**
     * Start the execution of activity
     * 
     * @return CBPActivityExecutionStatus
     */
    public function Execute()
    {
        $validationErrors = self::ValidateProperties(array_map(
            fn ($property) => $this->{$property["FieldName"]},
            self::getPropertiesDialogMap()
        ));

        if (!empty($validationErrors)) {
            foreach ($validationErrors as $error) {
                $this->WriteToTrackingService($error["message"], 0, CBPTrackingType::Error);
            }
            return CBPActivityExecutionStatus::Closed;
        }

        $conversationTitle = $this->ConversationTitle ?: self::getDefaultConversationTitle();
        $supportUserId = CBPHelper::ExtractUsers($this->SupportUser, $this->getDocumentId(), true);
        $employeeId = CBPHelper::ExtractUsers($this->Employee, $this->getDocumentId(), true);

        if ($privateChatId = $this->createPrivateChat($employeeId, $conversationTitle)) {
            if ($openlineChatId = $this->createOpenlineChat($employeeId, $privateChatId, $conversationTitle)) {
                $autoMessage = $this->AutoMessage ?: self::getDefaultAutoMessage();
                $openlineChat = new OpenLinesChat($openlineChatId);
                $openlineChat->transfer([
                    "TO" => $supportUserId
                ]);
                $openlineChat->sendAutoMessage(OpenLinesChat::TEXT_DEFAULT, $autoMessage);
                return CBPActivityExecutionStatus::Closed;
            }
        }

        foreach ($this->errorCollection as $error) {
            $this->WriteToTrackingService($error->getMessage(), 0, CBPTrackingType::Error);
        }

        return CBPActivityExecutionStatus::Closed;
    }

    /** 
     * Load modules
     * 
     * @return bool
     */
    protected function loadModules()
    {
        if (CModule::IncludeModule("im") && CModule::IncludeModule("imopenlines")) {
            return true;
        }
        return false;
    }

    /** 
     * Create private chat
     * 
     * @param string $userId
     * @return Int
     */
    protected function createPrivateChat($userId, $title)
    {
        $chatParams = [
            "TITLE" => $title,
            "TYPE" => Chat::IM_TYPE_PRIVATE,
            "ENTITY_TYPE" => Chat::ENTITY_TYPE_LIVECHAT,
            "AUTHOR_ID" => 0,
            "ENTITY_ID" => self::LINE_ID . "|" . $userId,
        ];

        $result = $this->chatFactory->addChat($chatParams);

        if ($result->isSuccess()) {
            return $result->getResult()["CHAT_ID"];
        }

        $this->errorCollection->add($result->getErrors());
        return null;
    }

    /** 
     * Create openline chat
     * 
     * @param string $userId
     * @return Int
     */
    protected function createOpenlineChat($connectorUserId, $connectorChatId, $title)
    {
        $chatParams = [
            "TITLE" => $title,
            "TYPE" => Chat::IM_TYPE_OPEN_LINE,
            "COLOR" =>  Color::getCodeByNumber($connectorUserId),
            "USERS" => [$connectorUserId],
            "ENTITY_TYPE" => Chat::ENTITY_TYPE_LINE,
            "ENTITY_ID" => self::CONNECTOR_ID . "|" . self::LINE_ID . "|" . $connectorChatId . "|" . $connectorUserId,
            "SKIP_ADD_MESSAGE" => "Y"
        ];

        $result = $this->chatFactory->addChat($chatParams);

        if ($result->isSuccess()) {
            return $result->getResult()["CHAT_ID"];
        }

        $this->errorCollection->add($result->getErrors());
        return null;
    }

    /**
     * Generate setting form
     * 
     * @param array $documentType
     * @param string $activityName
     * @param array $workflowTemplate
     * @param array $workflowParameters
     * @param array $workflowVariables
     * @param array $currentValues
     * @param string $formName
     * @return string
     */
    public static function GetPropertiesDialog($documentType, $activityName, $workflowTemplate, $workflowParameters, $workflowVariables, $currentValues = null, $formName = "", $popupWindow = null, $siteId = "")
    {
        $dialog = new PropertiesDialog(__FILE__, [
            "documentType" => $documentType,
            "activityName" => $activityName,
            "workflowTemplate" => $workflowTemplate,
            "workflowParameters" => $workflowParameters,
            "workflowVariables" => $workflowVariables,
            "currentValues" => $currentValues,
            "formName" => $formName,
            "siteId" => $siteId
        ]);
        $dialog->setMap(static::getPropertiesDialogMap($documentType));

        return $dialog;
    }

    /**
     * Process form submition
     * 
     * @param array $documentType
     * @param string $activityName
     * @param array &$workflowTemplate
     * @param array &$workflowParameters
     * @param array &$workflowVariables
     * @param array &$currentValues
     * @param array &$errors
     * @return bool
     */
    public static function GetPropertiesDialogValues($documentType, $activityName, &$workflowTemplate, &$workflowParameters, &$workflowVariables, $currentValues, &$errors)
    {
        $documentService = CBPRuntime::GetRuntime(true)->getDocumentService();
        $dialog = new PropertiesDialog(__FILE__, [
            "documentType" => $documentType,
            "activityName" => $activityName,
            "workflowTemplate" => $workflowTemplate,
            "workflowParameters" => $workflowParameters,
            "workflowVariables" => $workflowVariables,
            "currentValues" => $currentValues,
        ]);

        $properties = [];
        foreach (static::getPropertiesDialogMap($documentType) as $propertyKey => $propertyAttributes) {
            $field = $documentService->getFieldTypeObject($dialog->getDocumentType(), $propertyAttributes);
            if (!$field) {
                continue;
            }

            $properties[$propertyKey] = $field->extractValue(
                ["Field" => $propertyAttributes["FieldName"]],
                $currentValues,
                $errors
            );
        }

        $errors = static::ValidateProperties($properties, new CBPWorkflowTemplateUser(CBPWorkflowTemplateUser::CurrentUser));

        if (count($errors) > 0) {
            return false;
        }

        $currentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($workflowTemplate, $activityName);
        $currentActivity["Properties"] = $properties;

        return true;
    }

    /**
     * Validate user provided properties
     * 
     * @param array $testProperties
     * @param CBPWorkflowTemplateUser $user
     * @return array
     */
    public static function ValidateProperties($testProperties = [], CBPWorkflowTemplateUser $user = null)
    {
        $errors = [];

        foreach (static::getPropertiesDialogMap() as $propertyKey => $propertyAttributes) {
            if (CBPHelper::getBool($propertyAttributes['Required']) && CBPHelper::isEmptyValue($testProperties[$propertyKey])) {
                $errors[] = [
                    "code" => "emptyText",
                    "parameter" => $propertyKey,
                    "message" => Loc::getMessage("LOCAL_CSC_FIELD_NOT_SPECIFIED", ["#FIELD_NAME#" => $propertyAttributes["Name"]])
                ];
            }
        }

        return array_merge($errors, parent::ValidateProperties($testProperties, $user));
    }

    /** 
     * Get default conversation title
     * 
     * @return string
     */
    protected static function getDefaultConversationTitle()
    {
        return "ELTBG Support";
    }

    /** 
     * Get default auto message
     * 
     * @return string
     */
    protected static function getDefaultAutoMessage()
    {
        return "Hi, This conversation was created for support";
    }

    /**
     * User provided properties
     * 
     * @return array
     */
    protected static function getPropertiesDialogMap()
    {
        return [
            "ConversationTitle" => [
                "Name" => Loc::getMessage("LOCAL_CSC_CONVERSATION_TITLE_FIELD"),
                "FieldName" => "ConversationTitle",
                "Type" => FieldType::STRING,
                "Default" => self::getDefaultConversationTitle(),
                "Required" => false
            ],
            "AutoMessage" => [
                "Name" => Loc::getMessage("LOCAL_CSC_AUTO_MESSAGE_FIELD"),
                "FieldName" => "AutoMessage",
                "Type" => FieldType::STRING,
                "Default" => self::getDefaultConversationTitle(),
                "Required" => false
            ],
            "SupportUser" => [
                "Name" => Loc::getMessage("LOCAL_CSC_SUPPORT_USER_FIELD"),
                "FieldName" => "SupportUser",
                "Type" => FieldType::USER,
                "Required" => true
            ],
            "Employee" => [
                "Name" => Loc::getMessage("LOCAL_CSC_EMPLOYEE_FIELD"),
                "FieldName" => "Employee",
                "Type" => FieldType::USER,
                "Required" => true
            ],
        ];
    }
}

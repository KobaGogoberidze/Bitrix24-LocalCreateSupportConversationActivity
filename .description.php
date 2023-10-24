<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

$arActivityDescription = [
    "NAME" => Loc::getMessage("LOCAL_CSC_NAME"),
    "DESCRIPTION" => Loc::getMessage("LOCAL_CSC_DESCRIPTION"),
    "TYPE" => "activity",
    "CLASS" => "LocalCreateSupportConversationActivity",
    "JSCLASS" => "BizProcActivity",
    "CATEGORY" => [
        "OWN_ID" => "local",
        "OWN_NAME" => "Local",
    ]
];

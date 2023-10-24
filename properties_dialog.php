<?

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

foreach ($dialog->getMap() as $fieldId => $field) : ?>
    <tr id="field_<?= strtolower(htmlspecialcharsbx($field["FieldName"])) ?>_container">
        <td align="right" width="40%"><?= htmlspecialcharsbx($field["Name"]) ?>:</td>
        <td width="60%">
            <? $fieldType = $dialog->getFieldTypeObject($field) ?>
            <?= $fieldType->renderControl([
                "Form" => $dialog->getFormName(),
                "Field" => $field["FieldName"]
            ], $dialog->getCurrentValue($field["FieldName"]), true, 0) ?>
        </td>
    </tr>
<? endforeach ?>
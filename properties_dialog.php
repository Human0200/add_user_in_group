<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
?>

<tr>
    <td align="right" width="40%"><b><?=GetMessage("BPAG_USER_ID_LABEL")?></b> <span style="color:#FF0000;">*</span> :</td>
    <td width="60%">
        <?=CBPDocument::ShowParameterField("int", 'user_ids', $arCurrentValues['user_ids'], array('size' => '50'))?>
    </td>
</tr>
<tr>
    <td align="right" width="40%"><b><?=GetMessage("BPAG_GROUP_ID_LABEL")?></b> <span style="color:#FF0000;">*</span> :</td>
    <td width="60%">
        <?=CBPDocument::ShowParameterField("int", 'group_id', $arCurrentValues['group_id'], array('size' => '50'))?>
    </td>
</tr>
<tr>
    <td align="right" width="40%"><b>Модераторы</b> <span style="color:#FF0000;">*</span> :</td>
    <td width="60%">
        <?=CBPDocument::ShowParameterField("int", 'moderators', $arCurrentValues['moderators'], array('size' => '50'))?>
    </td>
</tr>
<tr>
    <td align="right" width="40%"><b>Владелец</b> <span style="color:#FF0000;">*</span> :</td>
    <td width="60%">
        <?=CBPDocument::ShowParameterField("int", 'owner', $arCurrentValues['owner'], array('size' => '50'))?>
    </td>
</tr>

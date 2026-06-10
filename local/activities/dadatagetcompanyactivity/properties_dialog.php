<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die(); ?>
<tr>
    <td align="right" width="40%">ИНН компании:</td>
    <td width="60%"><?= CBPDocument::ShowParameterField(
        'string',
        'inn',
        $arCurrentValues['inn'] ?? '',
        ['maxlength' => 12]
    ) ?></td>
</tr>

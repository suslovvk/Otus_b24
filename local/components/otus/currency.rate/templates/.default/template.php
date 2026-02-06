<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
?>
<div class="currency-rate">
    <h3>Курс валюты: <?= htmlspecialcharsbx($arResult['NAME']) ?></h3>
    <p><strong><?= $arResult['CURRENCY'] ?>:</strong> <?= $arResult['RATE'] ?></p>
</div>

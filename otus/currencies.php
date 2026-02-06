<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetTitle("Курсы валют");
?>

<?php
$APPLICATION->IncludeComponent(
    "otus:currency.rate",
    "",
    [
        "CURRENCY" => "RUB"
        // RUB
        // USD
        // EUR
    ]
);
?>

<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
use Bitrix\Main\Diag\Debug;

Debug::writeToFile(date('Y - m - d H:i:s'),'' , '/local/logs/test.log');

?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
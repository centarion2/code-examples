<?php
/**
 * Скрипт на крон удаляет записи хайлоада ApiRequests, которые старше UF_API_REQUEST_STORE_DAYS дней
 * За один запуск скрипт удаляет максимум 1000 записей
 */

use Bitrix\Main\Config\Option;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Application;

if(empty($_SERVER["DOCUMENT_ROOT"])) {
    $_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__)) . "/../..";;
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

// Отключаем буферизацию вывода.
while (ob_get_level()) {
    ob_end_flush();
}

echo "Скрипт начал свою работу" . PHP_EOL;

$iDays = (int) Option::get("askaron.settings", "UF_API_REQUEST_STORE_DAYS");

$obBxCompareDate = new DateTime();
$obBxCompareDate->add("-$iDays day");

Loader::includeModule('highloadblock');
$hlDataClass = HighloadBlockTable::compileEntity("ApiRequests")->getDataClass();

$obQuery = $hlDataClass::query()
    ->addSelect('ID')
    ->where('UF_DATE_CREATE', '<', $obBxCompareDate)
    ->addOrder('ID', 'ASC')
    ->setLimit(1000)
;

$obCollection = $obQuery->fetchCollection();

$obConnection = Application::getConnection();
$obConnection->startTransaction();

$iCount = 0;
foreach ($obCollection as $obItem) {
    $obItem->delete();
    $iCount++;
}

$obConnection->commitTransaction();

echo "Скрипт успешно отработал. Удалено $iCount записей" . PHP_EOL;
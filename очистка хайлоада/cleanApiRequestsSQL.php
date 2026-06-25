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

// Параметры скрипта

// Максимальное число удаляемых строк. Должно быть целым числом большим нуля
$iLimit = 1000;
// Сколько дней хранить АПИ-запросы. Должно быть целым числом большим нуля
$iDays = (int) Option::get("askaron.settings", "UF_API_REQUEST_STORE_DAYS");

if ($iDays <= 0) {
    echo "Ошибка. Некорректная настройка++ \"Сколько дней хранить АПИ-запросы\"" . PHP_EOL;
    die;
}


// Подготовка параметров SQL запросов
$connection = Application::getConnection();
$sqlHelper = $connection->getSqlHelper();

Loader::includeModule('highloadblock');
$hlDataClass = HighloadBlockTable::compileEntity("ApiRequests")->getDataClass();
$sTableName = $hlDataClass::getTableName();
if (empty($sTableName)) {
    echo "Ошибка. Некорректное название таблицы" . PHP_EOL;
    die;
}
// Приводим дату к безопасному SQL-формату
$obBxCompareDate = new DateTime();
$obBxCompareDate->add("-$iDays day");
$sCompareDateSql = $sqlHelper->convertToDbDateTime($obBxCompareDate);

// Запрос 1 для подсчета количества строк и отладки
$sSelectSql = "
    SELECT ID
    FROM {$sTableName}
    WHERE UF_DATE_CREATE < {$sCompareDateSql}
    ORDER BY ID ASC
    LIMIT " . (int) $iLimit
;
$rsData = $connection->query($sSelectSql);
$iRowsCount = $rsData->getSelectedRowsCount();
// while ($arRow = $rsData->fetch())
// {
//     debug($arRow['ID']);
// }

// Запрос 2 на очистку хайлоада
$sDeleteSql = "
    DELETE FROM {$sTableName}
    WHERE UF_DATE_CREATE < {$sCompareDateSql}
    ORDER BY ID ASC
    LIMIT " . (int) $iLimit
;
$connection->queryExecute($sDeleteSql); // ОСТОРОЖНО! Массовое удаление в БД

echo "Скрипт успешно отработал. Удалено $iRowsCount записей" . PHP_EOL;
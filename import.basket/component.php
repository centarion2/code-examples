<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Sale;

$maxRecords = 500; // Не больше 500 записей	
$OFFSET = 2; // Если считать с нуля то номер строки равен (индекс + OFFSET)		

//$filename = $_SERVER["DOCUMENT_ROOT"]."/local/components/vmgr/import.basket/input.csv";
if(isset($_FILES['file'])) {
	$filename = $_FILES['file']['tmp_name']; // Реально файл находится здесь. Он переименован

	$arItems = [];
	if(file_exists($filename)) {
		$file_info = pathinfo($_FILES['file']['name']); // Имя загружаемого файла
		$ext = $file_info['extension'];
		if($ext == 'csv') {
			if (($handle = fopen($filename, "r")) !== FALSE) {
				$count = 0;
				while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
					$count++;
					if($count == 1) continue; // Пропуск шапки

					$arItems[] = [
						"ARTICUL" => $data[0],
						"QUANTITY" => $data[1]
					];
					if($count >= $maxRecords + 1) { // Шапка не считается, поэтому +1
						break; // После 500 товарных позиций прекращаем чтение
					}
				}
				fclose($handle);
			}
		
		// Чтение с помощью библиотеки PHPExcel
		} elseif($ext === 'xlsx') {
			require_once $_SERVER["DOCUMENT_ROOT"].'/local/reports/excel/Classes/PHPExcel/IOFactory.php';
			// Файл xlsx
			$xls = PHPExcel_IOFactory::load($filename);
			// Первый лист
			$xls->setActiveSheetIndex(0);
			$sheet = $xls->getActiveSheet();
			
			$count = 0;
			foreach ($sheet->toArray() as $row) {
				$count++;
				if($count == 1) continue; // Пропуск шапки

				$arItems[] = [
					"ARTICUL" => trim($row[0]), // удаляем лишние пробелы
					"QUANTITY" => trim($row[1])
				];
				if($count >= $maxRecords + 1) { // Шапка не считается, поэтому +1
					break; // После 500 товарных позиций прекращаем чтение
				}
			}
			// Чтение файлов xls выдает ошибку: файл не читаемый
		/*} elseif($ext === 'xls') {
			require_once $_SERVER["DOCUMENT_ROOT"].'/local/lib/phpExcelReader/Excel/reader.php';
			// ExcelFile($filename, $encoding);
			$data = new Spreadsheet_Excel_Reader();
			// Set output Encoding.
			$data->setOutputEncoding('CP1251');
			$data->read($filename);
			$count = 0;
			for ($i = 1; $i <= $data->sheets[0]['numRows']; $i++) {
				$count++;
				if($count == 1) continue; // Пропуск шапки
				
				$arItems[] = [
					"ARTICUL" => trim($data->sheets[0]['cells'][$i][1]), // удаляем лишние пробелы
					"QUANTITY" => trim($data->sheets[0]['cells'][$i][2])
				];
				if($count >= $maxRecords + 1) { // Шапка не считается, поэтому +1
					break; // После 500 товарных позиций прекращаем чтение
				}
			}
			*/
		// Чтение с помощью библиотеки Nuovo Spreadsheet-Reader (проблема с загрузкой если файл не имеет расширения)
		/*} elseif($ext === 'xlsx') {
	
			// Подключение библиотеки Nuovo для чтения Excel файлов
			require_once $_SERVER["DOCUMENT_ROOT"].'/local/lib/composer/vendor/nuovo/spreadsheet-reader/php-excel-reader/excel_reader2.php';    
			require_once $_SERVER["DOCUMENT_ROOT"].'/local/lib/composer/vendor/nuovo/spreadsheet-reader/SpreadsheetReader.php';  
			//echo "Чтение файла<br>";
			// Файл xlsx, xls, csv, ods.
			$Reader = new SpreadsheetReader($filename);
			//echo " Файл прочитан<br>";
			// Номер листа.
			$Reader->ChangeSheet(0);
			
			foreach ($Reader as $Row) {
				if($count == 1) continue; // Пропуск шапки
				
				$arItems[] = [
					"ARTICUL" => $Row[0],
					"QUANTITY" => $Row[1]
				];
				if($count >= $maxRecords + 1) { // Шапка не считается, поэтому +1
					break; // После 500 товарных позиций прекращаем чтение
				}
			}*/
		}  else{
			$arResult["ERROR"] = 'Неправильный формат файла. Поддерживаются только файлы формата csv и xlsx.';
		}
	} else {
		$arResult["ERROR"] = "Файл не найден";
	}
	
	// Если ошибок при чтении файла нет то загружаем товары
	if(!isset($arResult["ERROR"])) {

		$arResult["ITEMS"] = $arItems;
		
		// Проверка правильности содержимого файла и получение ид товаров
		foreach($arResult["ITEMS"] as $line => &$product) {
			
			if(empty($product["QUANTITY"]) && empty($product["ARTICUL"])) {
				$product["ERROR"] = "Пустая строка";
				continue;
			}
			if(is_numeric($product["QUANTITY"])) {
				if($product["QUANTITY"] <= 0) {
					$product["ERROR"] = "Количенство должно быть больше нуля";
				}
			} else {
				$product["ERROR"] = "Неверное количество";
			}
			$arFilter = Array("IBLOCK_ID"=>28, "=PROPERTY_ART" => $product["ARTICUL"]);
			$res = CIBlockElement::GetList(Array(), $arFilter, false, false, Array("ID", "ACTIVE", "PROPERTY_NEW_SALE"));
			if ($ob = $res->GetNextElement()){;
				// Если товар с таким артикулом есть
				$arFields = $ob->GetFields(); // поля элемента
				$product["ID"] = $arFields["ID"]; // Записываем номер товара
				if($arFields["PROPERTY_NEW_SALE_VALUE"] == "Архив 1С") {
					$product["ERROR"] = "Товар находится в архиве"; 
				}
				if($arFields["ACTIVE"] != "Y") {
					$product["ERROR"] = "Товар неактивен";
				}
			} else {
				// Если товара с таким артикулом нет
				$product["ID"] = "";
				$product["ERROR"] = "Неверный артикул";
			}
		}
		
		// Получение другой информации о товарах: доступного количества и цены
		foreach($arResult["ITEMS"] as &$product) {
			if(!isset($product["ERROR"])) {
				$ar_res = CCatalogProduct::GetByID($product["ID"]);
				$product["AVAILABLE"] = $ar_res["AVAILABLE"];
				/*if($product["AVAILABLE"] != "Y") {
					$product["ERROR"] = "Товар недоступен";
				}*/
				$product["AVAILABLE_QUANTITY"] = $ar_res["QUANTITY"];
				$arPrice = CPrice::GetByID($product["ID"]);
				$product["PRICE"] = $arPrice["PRICE"];
			}
		}
	
		// Очистка корзины если выбран чекбокс
		$basket = Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), Bitrix\Main\Context::getCurrent()->getSite());
		if(isset($_REQUEST["clear"]) && $_REQUEST["clear"] == "yes") {
			foreach ($basket as $item)
			{
				$item->delete();
				$refreshStrategy = \Bitrix\Sale\Basket\RefreshFactory::create(\Bitrix\Sale\Basket\RefreshFactory::TYPE_FULL);
				$basket->refresh($refreshStrategy);    
				$basket->save();
			}
		}
		debug($arResult["ITEMS"], 'log');
		// Загрузка товаров в корзину
		foreach($arResult["ITEMS"] as &$product) {
			if(!isset($product["ERROR"])) { // ошибочные товары не загружаются
				/*
				// Загрузка товаров в корзину (старый вариант)
				$item = array(
					'PRODUCT_ID' => $product["ID"],
					'QUANTITY' => $product["QUANTITY"],
				);
				$basketResult = \Bitrix\Catalog\Product\Basket::addProduct($item);
				if ($basketResult->isSuccess())	{
					$product["IS_LOADED"] = "Y";
					$basket = \Bitrix\Sale\Basket::loadItemsForFUser(
						\Bitrix\Sale\Fuser::getId(), 
						\Bitrix\Main\Context::getCurrent()->getSite()
					);
					$refreshStrategy = \Bitrix\Sale\Basket\RefreshFactory::create(\Bitrix\Sale\Basket\RefreshFactory::TYPE_FULL);
					$basket->refresh($refreshStrategy);    
					$basket->save();
				} else {
					$product["IS_LOADED"] = "N";
				}*/
	
				// Аякс запрос на добавление товара в корзину
				$httpClient = new Bitrix\Main\Web\HttpClient();
				$httpClient->setHeader("X-Requested-With", "XMLHttpRequest", true);
				$res = $httpClient->post(
					(empty($_SERVER['HTTPS'])?'http':'https').'://'.$_SERVER["SERVER_NAME"].'/cart/index.php',
					array('text' => 'Товар в корзине', 'id' => $product["ID"], 'cnt' => $product["QUANTITY"], 'fuser' => CSaleBasket::GetBasketUserID())
				);
				if($res) {
					$product["IS_LOADED"] = "Y"; // Аякс добавления в корзину вернул успех
				} else {
					$product["IS_LOADED"] = "N"; // Аякс вернул ошибку
					$product["ERROR"] = "Ошибка при добавлении в корзину";
				}
			} else {
				$product["IS_LOADED"] = "N";
			}	
		}
		
		// Загрузка цен из корзины
		$basket = Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), Bitrix\Main\Context::getCurrent()->getSite());
		foreach($arResult["ITEMS"] as &$product) {
			if($product["IS_LOADED"] == "Y") {
				$isFind = false;
				foreach ($basket as $item)
				{
					if($item->getProductId()!=$product["ID"])
						continue;
					
					$isFind = true;
					$product["BASKET_PRICE"] = $item->getPrice();
					$product["BASKET_BASE_PRICE"] = $item->getBasePrice();
					$product["BASKET_DEFAULT_PRICE"] = $item->getDefaultPrice(); // цена по умолчанию
					// Цены не как в корзине
				}
				if($isFind)
					$product["IS_FIND_BASKET_PRICES"] = "Y"; // Товар с ценами найден в корзине
				else
					$product["IS_FIND_BASKET_PRICES"] = "N"; // Товар с ценами не найден в корзине (ошибка)
		
			}
		}
		
		// Сбор статистики работы компонента
		$arResult["ERROR_COUNT"] = 0; // Количество ошибочных записей в файле
		$arResult["LOADED_COUNT"] = 0; // Количество загруженных в корзину товаров
		$arResult["TOTAL_COUNT"] = 0; // Общее количество записей в файле
		foreach($arResult["ITEMS"] as &$product) {
			$arResult["TOTAL_COUNT"]++;
			if(isset($product["ERROR"])) {
				$arResult["ERROR_COUNT"]++;
			}
			if($product["IS_LOADED"] == "Y") {
				$arResult["LOADED_COUNT"]++;
			}			
		}
		
	
		// Статистика наличие/уточняйте/нет
		$arResult["IS_YES"] = 0;
		$arResult["IS_SPECIFY"] = 0;
		$arResult["IS_NO"] = 0;
		foreach($arResult["ITEMS"] as &$product) {
			if($product["IS_LOADED"] == "Y") {
				$quan = $product["QUANTITY"];
				$item = MDM::getElemsFromIblock(
					array("ID" => $product["ID"], "IBLOCK_ID" => IBLOCK_CATALOG, "ACTIVE" => "Y"),
					array("CATALOG_GROUP_1", "CATALOG_GROUP_2"),
					array("DIS_COUNT", "DIS_SKIDKA", "QUAN_SKIDKA", "QUAN_MIN_COUNT", "RESERVE", "LASTENTRY")
				)[$product["ID"]];
		
				if ($item["ID"]) {
					$p = $item["PROPERTIES"];
		
					if (ProductStock::CheckStore($_SESSION['GEO']['ID'])) {
						$quant = ProductStock::getByCity($item['ID'], $_SESSION['GEO']['ID']);
						$item['CATALOG_QUANTITY'] = $quant['AVAILABLE'];
						$p['RESERVE'] = $quant['RESERVE'];
					} else {
						$quant = ProductStock::getByCity($item['ID'], DEFAULT_CITY_HEAD);
						$item['CATALOG_QUANTITY'] = $quant['AVAILABLE'];
						$p['RESERVE'] = $quant['RESERVE'];
					}
					$p["RESERVE"] += $item["CATALOG_QUANTITY"]; 
					$product["AVAILABLE_TEXT"] = $item["CATALOG_QUANTITY"] >= $quan ? "ЕСТЬ" : ($p["RESERVE"] >= $quan ? "УТОЧНЯЙТЕ" : "НЕТ");
					if($product["AVAILABLE_TEXT"]=="ЕСТЬ") {
						$arResult["IS_YES"]++; // В наличии из загруженных товаров
					} elseif($product["AVAILABLE_TEXT"]=="УТОЧНЯЙТЕ") {
						$arResult["IS_SPECIFY"]++; // Уточняйте
					} else { // "НЕТ"
						$arResult["IS_NO"]++; // Отсутствует
					}
					
					
				}
			}
		}
		//debug($arResult["ITEMS"], 'log');
		
		// Лог незагруженных товаров (если product2 заменить на product то будет баг)
		$errorList = [];
		foreach($arResult["ITEMS"] as $line => $product2) {
			if(isset($product2["ERROR"])) {
				$errorList[] = "Строка ".strval($line+$OFFSET). // Номер строки равен индексу в массиве + 1 так как с нуля 
																// и еще + 1 так как учитываем шапку
					". Артикул '".$product2["ARTICUL"].
					"' количество '".$product2["QUANTITY"].
					"'. Ошибка: ".$product2["ERROR"].".";
			}
		}
		$arResult["ERRORS"] = $errorList;
		//debug($arResult["ITEMS"], 'log');

		if(!empty($arResult["ERRORS"])) {
			// Запись лога в текстовый файл
			$logContent = "";
			foreach($arResult["ERRORS"] as $strLine) {
				$logContent .= $strLine."\r\n";
			}
			$logContent = iconv("utf-8", 'windows-1251//IGNORE', $logContent);
			list($usec, $sec) = explode(" ", microtime());
			$arResult["LOG_FILE"] = '/upload/basketfromхls/'.strval($sec).'-'.strval($usec*100000000).'.txt';
			file_put_contents($_SERVER["DOCUMENT_ROOT"].'/upload/basketfromхls/'.$sec.'-'.strval($usec*100000000).'.txt', $logContent);
			$arResult["IS_LOG"] = "Y";
		} else {
			$arResult["IS_LOG"] = "N";
		}

		//LocalRedirect($arParams["PATH_TO_BASKET"]);
	} else {
		// Если ошибка при чтении файла  ничего не делаем
		// В шаблоне должна вывестись ошибка Result[ERROR]
	}
	
} else {
	// Если файл не загружен то ничего не передаем
	$arResult = null;
}

$this->IncludeComponentTemplate();
?>
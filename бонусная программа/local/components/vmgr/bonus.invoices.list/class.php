<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) exit('Prolog not included');
use \Bitrix\Highloadblock\HighloadBlockTable,
	\Bitrix\Main\Entity\Query;

class BonusInvoicesListComponent extends CBitrixComponent
{

	public function executeComponent()
    {
            $this->getResult();
			$this->includeComponentTemplate();
 	}

	protected function getResult()
    {
		// Параметры
		global $USER;
		// Подготовительные операции
		CModule::IncludeModule('iblock');
		require_once $_SERVER['DOCUMENT_ROOT'] . "/local/lib/vmgr/bonusprogram/bonusprogrammanager.php";
		$bp = Vmgr\BonusProgram\BonusProgramManager::get();
		$pageSize = $bp::BP_LOAD_ORDERS_PAGE_SIZE;; /* По сколько элементов загружаем. Должно быть 10
													Должно быть такое же значение как переменная pageSize 
													в /local/ajax/bp.php action=load_orders
													*/
		$iblockId = $bp->getInvoiceIblockId();
		$userId = $USER->GetID();
		//$userId = 1; // для юзер для теста

		// Блок "Детализация по бонусам"
		$arItems = [];
		$arFilter = ["IBLOCK_ID" => $iblockId, 'PROPERTY_USER' => $userId];
		$arSelect = [  
			'ID', 
			'NAME',
			'PROPERTY_USER',
			'PROPERTY_INVOICE',
			'PROPERTY_INVOICE_DATE',
			'PROPERTY_RETURN_DATE',
			'PROPERTY_CANCEL_DATE',
			'PROPERTY_INVOICE_AMOUNT',
			'PROPERTY_RETURN_AMOUNT',
			'PROPERTY_BONUS_PERCENT_ORIGINAL',
			'PROPERTY_BONUS_PERCENT',
			'PROPERTY_BONUS_FULL',
			'PROPERTY_BONUS_SPENT',
			'PROPERTY_BONUS_AVAILABLE'
		];
		$res = \CIBlockElement::GetList(['PROPERTY_INVOICE_DATE' => 'DESC'], $arFilter, false, ["iNumPage" => 1, "nPageSize" => $pageSize], $arSelect);
		
		while($arFields = $res->Fetch()) {
			$arItems[] = $arFields;
		}
		$this->arResult["ITEMS"] = [];
		$warningPeriod = $bp->getSettingsArray()["WARNING_PERIOD"]; // 31 день
		foreach($arItems as $arItem) {
			// Дата отмены если есть
			if(!empty($arItem["PROPERTY_CANCEL_DATE_VALUE"])) {
				$dateCancel = (new \DateTime($arItem["PROPERTY_CANCEL_DATE_VALUE"]))->format("d.m.Y");
			} else {
				$dateCancel = "";
			}
			// Статусы
			if(!$bp->compareDate($arItem["PROPERTY_RETURN_DATE_VALUE"])) {
				// Если дата возврата не прошла
				$status = 'not_available';
			} elseif(intval($arItem["PROPERTY_BONUS_AVAILABLE_VALUE"]) <> 0) {

				if(!empty($dateCancel) && $bp->compareWarningDate($dateCancel, $warningPeriod)) {
					// Если скоро сгорят баллы предупреждение
					$status = 'warning';
				}else {
					// Если дата возврата прошла и есть неиспользованные бонусы то статус
					$status = 'available';
				}
				
			} else {
				// Если дата возврата прошла и все бонусы потрачены
				$status = 'none';
			}
			$dateAvailable = (new DateTime($arItem["PROPERTY_RETURN_DATE_VALUE"]))
				->modify('1 day')
				->format("d.m.Y");
			$this->arResult["ITEMS"][] = [
				"NAME" => $arItem["PROPERTY_INVOICE_VALUE"],
				"DATE" => $arItem["PROPERTY_INVOICE_DATE_VALUE"],
				"SUM" => $arItem["PROPERTY_INVOICE_AMOUNT_VALUE"],
				"SUM_RETURN" => $arItem["PROPERTY_RETURN_AMOUNT_VALUE"],
				"SUM_CREDIT" => $arItem["PROPERTY_INVOICE_AMOUNT_VALUE"] - $arItem["PROPERTY_RETURN_AMOUNT_VALUE"],
				"BONUS_FULL" => $arItem["PROPERTY_BONUS_FULL_VALUE"],
				"BONUS_AVAILABLE" => $arItem["PROPERTY_BONUS_AVAILABLE_VALUE"],
				"BONUS_SPENT" => $arItem["PROPERTY_BONUS_SPENT_VALUE"],
				"DATE_AVAILABLE" => $dateAvailable,
				"DATE_CANCEL" => $dateCancel,
				"STATUS" => $status, // Статус
			];
		}
		$this->arResult["LAST"] = $res->NavPageNomer == $res->NavPageCount;

		// Сгорание бонусов
		$min = $warningPeriod;
		foreach($this->arResult["ITEMS"] as $arItem) {
			if($arItem["STATUS"] == 'warning')
			{
				$diff = $bp->getDateDifference($arItem["DATE_CANCEL"]);
				if($diff <= $min) {
					$warningDate = $arItem["DATE_CANCEL"]; // Дата ближайшего сгорания
					$min = $diff;
				}
				
			}
		}
		// Ближайшие накладные для аннулирования
		$warningItems = [];
		foreach($this->arResult["ITEMS"] as $arItem) {
			if($arItem["STATUS"] == 'warning' && $bp->getDateDifference($arItem["DATE_CANCEL"]) == $min)
			{
				$warningItems[] = $arItem;
			}
		}
		// Число бонусов для аннулирования
		if(!empty($warningItems)) {
			$warningBonus = 0;
			foreach($warningItems as $arItem) {
				$warningBonus += $arItem["BONUS_AVAILABLE"]; // сколько сгорят бонусов
			}
		} else {
			$warningBonus = 0;
		}
		
		$this->arResult["WARNING_BONUS"] = $warningBonus; // Ближайшие бонусы, которые сгорят
		$this->arResult["WARNING_DATE"] = $warningDate; // Дата ближайшего сгорания

		// Суммарные количество недоступных бонусов с датой начисления
		$flag = true;
		$totalNoCredit = 0;
		foreach($this->arResult["ITEMS"] as $arItem) {
			if($arItem["STATUS"] == 'not_available') {
				$totalNoCredit += $arItem["BONUS_FULL"];
				// Вычисляем самую позднюю дату начисления
				if($flag) {
					$flag = false;
					$dateCredit = $arItem["DATE_AVAILABLE"];
				}
				if(strtotime($arItem["DATE_AVAILABLE"]) > strtotime($dateCredit)) {
					$dateCredit = $arItem["DATE_AVAILABLE"];
				}
					
			}
		}
		// Общее количество доступных бонусов
		$totalCredit = 0;
		foreach($this->arResult["ITEMS"] as $arItem) {
			if($arItem["BONUS_AVAILABLE"] > 0) {
				$totalCredit += $arItem["BONUS_AVAILABLE"];
			}
		}
		$this->arResult["TOTAL_CREDIT"] = $totalCredit;
		$this->arResult["TOTAL_NO_CREDIT"] = $totalNoCredit;
		$this->arResult["TOTAL"] = $totalNoCredit + $totalCredit;
		$this->arResult["DATE_CREDIT"] = $dateCredit;
		$this->arResult["SETTINGS"] = $bp->getSettingsArray();
	}
}

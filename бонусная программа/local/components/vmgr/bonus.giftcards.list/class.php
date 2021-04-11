<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) exit('Prolog not included');
use \Bitrix\Highloadblock\HighloadBlockTable,
	\Bitrix\Main\Entity\Query;

class SearchPhraseStatisticComponent extends CBitrixComponent
{

	public function executeComponent()
    {
		
        $this->getResult();
		$this->includeComponentTemplate();
    }

	protected function getResult()
    {
		global $USER;
		// Подготовительные операции
		CModule::IncludeModule('iblock');
		require_once $_SERVER['DOCUMENT_ROOT'] . "/local/lib/vmgr/bonusprogram/bonusprogrammanager.php";
		$bp = Vmgr\BonusProgram\BonusProgramManager::get();
		$iblockId = $bp->getGiftCardsIblockId();
		$userId = $USER->GetID();
		$rsUser = CUser::GetByID($userId);
		$arUser = $rsUser->GetNext(false);
		//debug($arUser, false, true);
		$this->arResult["EMAIL"] = $arUser["EMAIL"];
		$this->arResult["PHONE"] = $arUser["PERSONAL_PHONE"];
		//$userId = 1; // для юзер для теста
		// Загрузка подарочных сертификатов
		$arSelect = [
			"ID",		
			"NAME",
			"DESCRIPTION", 
			"DETAIL_PICTURE",
			"UF_PRODUCT_ID",
			"UF_URL",
			"UF_EXPIRY_DATE",
			"UF_INSTRUCTION",
		];
		$res = \CIBlockSection::GetList(array('SORT' => 'ASC'), array('IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'), false, $arSelect);
		$arItems = [];
		while($fields = $res->Fetch()) {
			$arItems[] = $fields;
		}

		foreach($arItems as &$arItem) {
			$arItem["PICTURE"] = CFile::GetPath($arItem["DETAIL_PICTURE"]);
			$arSelect = [
				"NAME",
				"PROPERTY_PRICE",
			];
			$res2 = \CIBlockElement::GetList(array('PROPERTY_PRICE' => 'DESC'), array('IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', '=IBLOCK_SECTION_ID' => $arItem["ID"]), false, false, $arSelect);
			// Номиналы не загружаются
			$nominals = [];
			while($fields = $res2->Fetch()) {
				$nominals[] = [
					"NOMINAL" => intval($fields["NAME"]),
					"NOMINAL_VALUE" => CurrencyFormatNumber(intval($fields["NAME"]), "RUB"),
					"PRICE" => floatval($fields["PROPERTY_PRICE_VALUE"]),
				];
			}
			$arItem["NOMINALS"] = $nominals;
		}

		// Вычисление количества доступных бонусов
		$bp = Vmgr\BonusProgram\BonusProgramManager::get();
		$invoiceIblockId = $bp->getInvoiceIblockId();
		$arFilter = ["IBLOCK_ID" => $invoiceIblockId, 'PROPERTY_USER' => $userId, '>PROPERTY_BONUS_AVAILABLE' => 0];
		$arSelect = ['PROPERTY_BONUS_AVAILABLE'];
		$res = \CIBlockElement::GetList([], $arFilter, $arSelect, false);
		$totalCredit = 0;
		while($arFields = $res->Fetch()) {
			$totalCredit += $arFields["PROPERTY_BONUS_AVAILABLE_VALUE"];
		}
		
		// Убираем недоступные номиналы
		foreach($arItems as $key1 => $arItem2) {
			foreach($arItem2["NOMINALS"] as $key2 => $nominal) {
				if($nominal["PRICE"] > $totalCredit) {
					unset($arItems[$key1]["NOMINALS"][$key2]);
				}
			}
			if(empty($arItems[$key1]["NOMINALS"])) {
				unset($arItems[$key1]); // и недоступные сертификаты
			}
		}

		//debug($arItems, false, true);
		$this->arResult["TOTAL_CREDIT"] = CurrencyFormatNumber($totalCredit, "RUB");		
		$this->arResult["ITEMS"] = $arItems;

		// Оплата
		$bp = Vmgr\BonusProgram\BonusProgramManager::get();
		$this->arResult["SETTINGS"] = $bp->getSettingsArray();
		$amount = $this->arResult["SETTINGS"]["PRICE"]; // 6 руб или 1 руб
		// Тело запроса Cload Payments
		$customerReceipt['Items'] = array();
		$customerReceipt['Items'][] = array(
			'label' => 'Доставка',
			'price' => $amount,
			'quantity' => 1,
			'amount' => $amount,
			'vat' => 118
		);
		// email пользователя
		if ($user = CUser::GetByID($USER->GetID())->fetch()) {
			if(!empty($user['EMAIL'])){
				$customerReceipt['Email'] = $user['EMAIL'];
			}
		}
		$this->arResult["CUSTOMER_RECEIPT"] = $customerReceipt;
		
		// Подключение скриптов JS
		\Bitrix\Main\Page\Asset::getInstance()->addJs("https://widget.cloudpayments.ru/bundles/cloudpayments", true);

		$this->arResult["BUY_DISABLED"] = $bp->isBuyDisabled();
	}
}

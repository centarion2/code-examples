<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) exit('Prolog not included');

class SearchPhraseStatisticComponent extends CBitrixComponent
{

	public function executeComponent()
    {
		if ($this->StartResultCache($this->arParams['CACHE_TIME'] ?? 86400))
		{
            $this->getResult();
			$this->includeComponentTemplate();

			if ($this->arParams['CONSOLE_DEBUG'] == 'Y')
			{
				echo "<script>console.log('{$this->GetName()} - No cache');</script>";
			}
		}
		else
		{
			if ($this->arParams['CONSOLE_DEBUG'] == 'Y')
			{
				echo "<script>console.log('{$this->GetName()} - FROM cache');</script>";
			}
		}
    }

	protected function getResult()
    {
		CModule::IncludeModule('search');
		// Знвчения по умолчанию для параметров компонента
		$this->arParams["COUNT_FROM"] = isset($this->arParams["COUNT_FROM"])? intval($this->arParams["COUNT_FROM"]):10;
		$this->arParams["PHRASE_COUNT"] = isset($this->arParams["PHRASE_COUNT"])? intval($this->arParams["PHRASE_COUNT"]):100;
		$this->arParams["MIN_LENGTH"] = isset($this->arParams["MIN_LENGTH"])? intval($this->arParams["MIN_LENGTH"]):4;
		// Выборка из базы
		$arItems = [];
		$arFilter = ["PAGES" => 1];
		$arSelect = ["ID", "PHRASE", "RESULT_COUNT", "PAGES", "TIMESTAMP_X"];
		$res = \CSearchStatistic::GetList(["TIMESTAMP_X" => "ASC"], $arFilter, $arSelect, false);
		//debug($res, false, true);
		while($arFields = $res->Fetch()) {
			$arItems[] = $arFields;
		}
		// Оставляется последнее из одинаковых поисковых фраз, считается количество запросов
		foreach($arItems as $arItem) {
			if(isset($arPhrases[$arItem["PHRASE"]])) {
				$arPhrases[$arItem["PHRASE"]] = [
					"DATA" => $arItem,
					"COUNT" => $arPhrases[$arItem["PHRASE"]]["COUNT"] + 1
				];
			} else {
				$arPhrases[$arItem["PHRASE"]] = [
					"DATA" => $arItem,
					"COUNT" => 1
				];
			}
		}
		// Удаляются лишние поисковые фразы
     	foreach($arPhrases as $key => $arPhrase) {
			if($arPhrase["DATA"]["PAGES"] == 0 // Запросы ничего не возвращают 
					|| $arPhrase["DATA"]["RESULT_COUNT"] == 0 // Невозможно определить количество товаров
					|| $arPhrase["COUNT"] < $this->arParams["COUNT_FROM"] //фильтр по количеству запросов (>=COUNT_FROM)
					|| strlen($arPhrase["DATA"]["PHRASE"]) < $this->arParams["MIN_LENGTH"]) // Ограничение длины запроса 
			{ 
				unset($arPhrases[$key]);
			}
		}
		// Сортировка по убыванию количества запросов
		function cmp($a, $b)
		{
			$origin = $a["COUNT"];
			$target = $b["COUNT"];
			if ($origin == $target) {
				return 0;
			}
			return ($origin > $target) ? -1 : 1;
		}
		usort($arPhrases, "cmp");
		// Только определенное число запросов выводится, остальные убираются из выборки
		$index = 0;
		foreach($arPhrases as $key => $value) {
			$index++;
			if($index > $this->arParams["PHRASE_COUNT"]) {
				unset($arPhrases[$key]);
			}
		}
		unset($index);

		$this->arResult["ITEMS"] = $arPhrases;
		$this->arResult["URL"] = CSearchStatistic::GetCurrentURL();
		
	}
}

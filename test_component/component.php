<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();

if(isset($_REQUEST['d'])) {
	
	$input_date = $_REQUEST['d'];
	
	;
	if (CModule::IncludeModule('iblock')) {
	$iblock = $arParams['IBLOCK'];
	$arSort= Array("NAME"=>"ASC");
	$arSelect = Array("ID","NAME", "PROPERTY_".$arParams['PROPERTY_ID']);
	$arFilter = Array("IBLOCK_ID" => $iblock);
	$res =  CIBlockElement::GetList($arSort, $arFilter, false, false, $arSelect);
	$data = [];
	while($ob = $res->GetNextElement()){
		$arFields = $ob->GetFields();
		$data[] = $arFields['PROPERTY_'.$arParams['PROPERTY_ID'].'_VALUE']; // 188 - идентификатор строкового свойства "Дата нерабочего дня"
	}
	}
	
	$input_date = new DateTime($input_date); 
	while(true) {
		$prev_date = clone $input_date;
		foreach($data as $day) {
			if(intval($input_date->diff(new DateTime($day))->format('%R%a')) == 0) {
				$input_date->add(new DateInterval('P1D'));
				break;
			}
		}
		switch($input_date->format('l')){
			case 'Saturday':
				$input_date->add(new DateInterval('P1D'));
			break;
			case 'Sunday':
				$input_date->add(new DateInterval('P1D'));
			break;
		}
		if(intval($input_date->diff($prev_date)->format('%R%a')) == 0)
			break;
	}
	$arResult = array();
	$arResult['RESULT'] = $input_date->format('d.m.Y');
	$arResult['DATA'] = $data;
} else {
	$arResult = [];
}
// подключаем шаблон компонента
$this->IncludeComponentTemplate();
<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

$arParams["SECTION_MAX_COUNT"] = (int) $arParams["SECTION_MAX_COUNT"];

// load sections
$arFilter = array(    
    'ACTIVE' => 'Y',
    'IBLOCK_ID' => $arParams['IBLOCK_ID'],
    'GLOBAL_ACTIVE'=>'Y',
    '=DEPTH_LEVEL' => 1
);
$arSelect = array();
$arOrder = array('SORT'=>'ASC');
$rsSections = CIBlockSection::GetList($arOrder, $arFilter, false, $arSelect);
while ($arSection = $rsSections->Fetch())
{
    if (strlen($arParams["SECTION_CODE"]) > 0 && $arSection["CODE"] == $arParams["SECTION_CODE"]) {
        continue;
    } 
        

    $arSection["SECTION_PAGE_URL"] = str_replace(
        "#SECTION_CODE#", 
        $arSection["CODE"], 
        $arSection["SECTION_PAGE_URL"]
    );

    $arResult["SECTIONS"][$arSection["ID"]] = $arSection;

    $arResult["SECTIONS"][$arSection["ID"]]["ITEMS"] = array();

    foreach($arResult["ITEMS"] as $arItem)
    {
        if ($arItem["CODE"] == $arParams["ELEMENT_CODE"])  {
            continue;
        }

        if ($arItem["IBLOCK_SECTION_ID"] == $arSection["ID"])
        {
            $arResult["SECTIONS"][$arSection["ID"]]["ITEMS"][$arItem["ID"]] = $arItem;
        }
        
        // Maximum products in current section
        if (count($arResult["SECTIONS"][$arSection["ID"]]["ITEMS"]) >= $arParams["SECTION_MAX_COUNT"])
        {
            break;
        }
    }
    if ($arResult["SECTIONS"][$arSection["ID"]]["ITEMS"]) {
        if(!$arResult["DEFAULT_SECTION"])
        {
            $arResult["DEFAULT_SECTION"] = $arSection;
        }
    } 
    else
    {
        // empty section don`t visible
        unset($arResult["SECTIONS"][$arSection["ID"]]);
    }
}
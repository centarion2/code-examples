<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

if (CModule::IncludeModule("iblock"))
{
    // Загрузка разделов, к которому принадлежит каждый элемент
    foreach($arResult["ITEMS"] as &$arItem)
    {
        $arItem["SECTIONS"] = [];

        $db_groups = CIBlockElement::GetElementGroups($arItem["ID"], true);
        while($ar_group = $db_groups->Fetch()) {
            $arItem["SECTIONS"][] = $ar_group;
        }
    }
    unset($arItem);

    // Блок "Все разделы"
    $arResult["ALL_SECTIONS"] = [];
    $arFilter = ['IBLOCK_ID' => $arParams["IBLOCK_ID"], 'GLOBAL_ACTIVE'=>'Y'];
    $rsSections = CIBlockSection::GetList(Array($by=>$order), $arFilter, true);
    while($arSection = $rsSections->GetNext())
    {
        $arResult["ALL_SECTIONS"][] = $arSection;
    }
}
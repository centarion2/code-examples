<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use \Bitrix\Main\Localization\Loc;

$this->AddEditAction($arParams['ID'], $arParams['EDIT_LINK'], CIBlock::GetArrayByID($arParams["IBLOCK_ID"], "ELEMENT_EDIT"));
$this->AddDeleteAction($arParams['ID'], $arParams['DELETE_LINK'], CIBlock::GetArrayByID($arParams["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
?>

<a id="<?=$this->GetEditAreaId($arParams['ID']);?>" href="<?=$arParams["DETAIL_PAGE_URL"]?>" class="product-preview">
    <div class="product-preview__img">
        <img class="lazy-img" data-src="<?=$arParams["FIELDS"]["PREVIEW_PICTURE"]["SRC"]?>" alt="<?=$arParams["FIELDS"]["PREVIEW_PICTURE"]["ALT"]?>">
    </div>
    <div class="product-preview__block">
        <h3 class="product-preview__title"><?=$arParams["NAME"]?></h3>
        <div class="product-preview__charac">
            <div class="product-preview__charac-item">
                <p class="product-preview__charac-val"><?=Loc::getMessage("MAIN_PRODUCTS_MATERIAL_LABEL")?></p>
                <p class="between-properties"></p>
                <p class="product-preview__charac-val"><?=$arParams["DISPLAY_PROPERTIES"]["MATERIAL"]["DISPLAY_VALUE"]?></p>
            </div>
            <div class="product-preview__charac-item">
                <p class="product-preview__charac-val"><?=Loc::getMessage("MAIN_PRODUCTS_WEIGHT_LABEL")?></p>
                <p class="between-properties"></p>
                <p class="product-preview__charac-val"><?=$arParams["DISPLAY_PROPERTIES"]["WEIGHT"]["DISPLAY_VALUE"]?></p>
            </div>
        </div>
    </div>
</a>
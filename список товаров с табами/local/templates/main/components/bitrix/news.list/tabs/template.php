<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */
$this->setFrameMode(true);

use \Bitrix\Main\Localization\Loc;
?>

<section class="<?=$arParams["WRAPPER_CLASS"]?>">
    <div class="our-product__top">
        <div class="container">
            <h2 class="h2-title-center"><?=$arParams["TITLE"]?></h2>

            <span class="resize"></span>
            <div class="our-product__tabs-wrapper">
                <div class="our-product__row our-product__tabs tab-buttons">
					<?foreach($arResult['SECTIONS'] as $sectionId => $arSection):?>
						<div data-type="<?=$arSection["ID"]?>" class="our-product__tab tab-button <?=($arSection["ID"] == $arResult["DEFAULT_SECTION"]["ID"])?'active':''?>">
							<p><?=$arSection["NAME"]?></p>
						</div>
					<?endforeach?>
                </div>
            </div>
        </div>
    </div>
    <div class="our-product__line">
        <div class="container">
            <div class="tab-line"></div>
        </div>

    </div>

    <div class="our-product__bottom">
        <div class="container">
            <div class="our-product__contents tab-contents">
				<?foreach($arResult['SECTIONS'] as $sectionId => $arSection):?>
					<div data-type="<?=$arSection["ID"]?>" class="tab-content <?=($arSection["ID"] == $arResult["DEFAULT_SECTION"]["ID"])?'active':''?>">
						<div class="our-product__content owl-carousel">
							<?foreach($arSection['ITEMS'] as $itemId => $arItem):?>
								
								<?$APPLICATION->IncludeComponent(
                                    "news.item", 
                                    "", 
                                    $arItem,
                                    false
                                );?>

							<?endforeach?>
						</div>
                        
                        <?if ($arParams["SHOW_BUTTON"] == 'Y'):?>
						    <a href="<?=$arSection["SECTION_PAGE_URL"]?>" class="button_1 our-product__button product-button"><?=Loc::getMessage("MAIN_PRODUCTS_MORE_BUTTON")?></a>
                        <?endif?>
					</div>
				<?endforeach?>
            </div>
        </div>
    </div>
</section>
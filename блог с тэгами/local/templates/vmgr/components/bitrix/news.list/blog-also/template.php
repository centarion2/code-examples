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

if (count($arResult["ITEMS"]) > 0):
?>
    <span class="blog__sections-title blog__sections-title--additional">Читайте также</span>
        
    <div class="blog__also">
        <?foreach($arResult["ITEMS"] as $arItem):?>
        <div class="blog__also-item">
            <span class="blog__also-title"><?= $arItem["NAME"] ?></span>
            <span class="blog__item-date blog__also-date"><?= $arItem["DISPLAY_ACTIVE_FROM"]?></span>
            <img src="<?=$arItem["PREVIEW_PICTURE"]["SRC"]?>"
                width="<?=$arItem["PREVIEW_PICTURE"]["WIDTH"]?>"
                height="<?=$arItem["PREVIEW_PICTURE"]["HEIGHT"]?>"
                alt="<?=$arItem["PREVIEW_PICTURE"]["ALT"]?>"
                title="<?=$arItem["PREVIEW_PICTURE"]["TITLE"]?>"  
                class="blog__also-img">
            <span class="blog__item-text blog__also-text"><?echo $arItem["PREVIEW_TEXT"];?></span>
            <a href="<?= $arItem["DETAIL_PAGE_URL"] ?>" class="blog__item-link blog__also-link">Подробнее</a>
        </div>
        <?endforeach;?>
    </div>

    <div class="blog__also blog__also-mobile">
        <div class="slider-container" id="project-slider-mob">
            <div class="swiper-container">
                <div class="swiper-wrapper">
                    <?foreach($arResult["ITEMS"] as $arItem):?>
                        <div class="swiper-slide blog__also-item">
                            <div>
                                <span class="blog__also-title"><?= $arItem["NAME"] ?></span>
                                <span class="blog__item-date blog__also-date"><?= $arItem["DISPLAY_ACTIVE_FROM"]?></span>
                                <img src="<?=$arItem["PREVIEW_PICTURE"]["SRC"]?>"
                                    width="<?=$arItem["PREVIEW_PICTURE"]["WIDTH"]?>"
                                    height="<?=$arItem["PREVIEW_PICTURE"]["HEIGHT"]?>"
                                    alt="<?=$arItem["PREVIEW_PICTURE"]["ALT"]?>"
                                    title="<?=$arItem["PREVIEW_PICTURE"]["TITLE"]?>" 
                                    class="blog__also-img">
                                <span class="blog__item-text blog__also-text"><?echo $arItem["PREVIEW_TEXT"];?></span>
                            </div>
                            
                            <a href="<?= $arItem["DETAIL_PAGE_URL"] ?>" class="blog__item-link blog__also-link">Подробнее</a>
                        </div>
                    <?endforeach;?>
                </div>
            </div>
            <div class="swiper-pagination swiper-pagination-mob"></div>
        </div>
    </div>
<?endif;?>
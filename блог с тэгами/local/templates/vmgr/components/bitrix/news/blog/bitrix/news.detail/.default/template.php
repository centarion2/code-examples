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
// debug($arResult);
// урл для поделиться
$protocol = ($_SERVER["HTTPS"] == "on") ? 'https://' : 'http://';
$curDetailUrl = $protocol . $_SERVER["SERVER_NAME"] . '/blog/' . $arParams["SECTION_CODE"] . '/' . $arResult["CODE"] . '/';
?>
<link rel="stylesheet" href="/local/assets/css/swiper.css">

<div class="blog-meta">
    <div class="blog-meta__wrapper wrapper">
        <h1 class="common-h">БЛОГ</h1>
    </div>
</div>

<div class="wrapper">
    <div class="blog__container">
        <div class="blog__detail-wrapper">
            <article class="blog__detail">
                <h2 class="blog__item-title"><?=$arResult["NAME"]?></h2>

                <div class="blog__detail-info">
                    <div class="blog__tags">
						<? foreach ($arResult["SECTIONS"] as $arSection):?>
							<a href="/blog/<?= $arSection["CODE"] ?>/" class="blog__tags-item"><?= $arSection["NAME"] ?></a>
						<?endforeach;?>
                    </div>
                    <span class="blog__item-date"><?=$arResult["DISPLAY_ACTIVE_FROM"]?></span>
                </div>
                
                <div class="blog__detail-content">
					<?if(strlen($arResult["DETAIL_TEXT"])>0):?>
						<?echo $arResult["DETAIL_TEXT"];?>
					<?else:?>
						<?echo $arResult["PREVIEW_TEXT"];?>
					<?endif?>                
				</div>

                <div class="blog__promo">
                    <div class="blog__promo-text">
                        <span class="blog__promo-title">Понравилась статья?</span>
                        <div class="blog__promo-subtitle">Поделитесь ссылкой с друзьями и коллегами!</div>
                    </div>
                    <div class="blog__promo-links">
                        <a href="#" class="blog__promo-link">
                            <img src="/img/wa.svg" alt="" class="blog__promo-logo">
                        </a>
                        <a href="#" class="blog__promo-link">
                            <img src="/img/tg.svg" alt="" class="blog__promo-logo">
                        </a>
                        <a href="https://vk.com/share.php?url=<?= $curDetailUrl ?>" target="_blank" class="blog__promo-link">
                            <img src="/img/vk.svg" alt="" class="blog__promo-logo">
                        </a>
                    </div>
                </div>
            </article>
        </div>

        <div class="blog__sections">
            <div class="blog__sections-sticky">
                <h3 class="blog__sections-title">Все разделы</h3>

                <div class="blog__sections-list">
					<? foreach ($arResult["ALL_SECTIONS"] as $arSection): 
						$active = ($arParams["SECTION_CODE"] == $arSection["CODE"]) ? "blog__sections-item--active" : "";
						?>
						<a href="/blog/<?= $arSection["CODE"] ?>/" class="blog__sections-item <?= $active ?>"><?= $arSection["NAME"] ?></a>
					<? endforeach; ?>
                </div>

                <?php
                // Фильтр блока "Читайте также"
                $sectionIds = [];
                foreach ($arResult["SECTIONS"] as $arSection)
                {
                    $sectionIds[] = $arSection["ID"];
                }
                global $arrFilter;
                $arrFilter = [
                    "!ID" => $arResult["ID"], // исключаем текущую статью
                    "SECTION_ID" => $sectionIds, // Берем статьи из разделов, к которой привязана текущая статья
                ];
                ?>

                <?$APPLICATION->IncludeComponent("bitrix:news.list","blog-also",Array(
                    "DISPLAY_DATE" => "Y",
                    "DISPLAY_NAME" => "Y",
                    "DISPLAY_PICTURE" => "Y",
                    "DISPLAY_PREVIEW_TEXT" => "Y",
                    "AJAX_MODE" => "N",
                    "IBLOCK_TYPE" => $arParams["IBLOCK_TYPE"],
                    "IBLOCK_ID" => $arParams["IBLOCK_ID"],
                    "NEWS_COUNT" => "3",
                    "SORT_BY1" => "ACTIVE_FROM",
                    "SORT_ORDER1" => "DESC",
                    "SORT_BY2" => "SORT",
                    "SORT_ORDER2" => "ASC",
                    "FILTER_NAME" => "arrFilter",
                    "FIELD_CODE" => Array(""),
                    "PROPERTY_CODE" => Array(""),
                    "CHECK_DATES" => "Y",
                    "DETAIL_URL" => "",
                    "PREVIEW_TRUNCATE_LEN" => "",
                    "ACTIVE_DATE_FORMAT" => "d.m.Y",
                    "SET_TITLE" => "N",
                    "SET_BROWSER_TITLE" => "N",
                    "SET_META_KEYWORDS" => "N",
                    "SET_META_DESCRIPTION" => "N",
                    "SET_LAST_MODIFIED" => "N",
                    "INCLUDE_IBLOCK_INTO_CHAIN" => "N",
                    "ADD_SECTIONS_CHAIN" => "N",
                    "HIDE_LINK_WHEN_NO_DETAIL" => "N",
                    "PARENT_SECTION" => "",
                    "PARENT_SECTION_CODE" => "",
                    "INCLUDE_SUBSECTIONS" => "N",
                    "CACHE_TYPE" => "A",
                    "CACHE_TIME" => "3600",
                    "CACHE_FILTER" => "Y",
                    "CACHE_GROUPS" => "N",
                    "DISPLAY_TOP_PAGER" => "N",
                    "DISPLAY_BOTTOM_PAGER" => "N",
                    "PAGER_TITLE" => "Статьи",
                    "PAGER_SHOW_ALWAYS" => "N",
                    "PAGER_TEMPLATE" => "",
                    "PAGER_DESC_NUMBERING" => "Y",
                    "PAGER_DESC_NUMBERING_CACHE_TIME" => "36000",
                    "PAGER_SHOW_ALL" => "Y",
                    "PAGER_BASE_LINK_ENABLE" => "Y",
                    "SET_STATUS_404" => "N",
                    "SHOW_404" => "N",
                    "MESSAGE_404" => "",
                    "PAGER_BASE_LINK" => "",
                    "PAGER_PARAMS_NAME" => "arrPager",
                    "AJAX_OPTION_JUMP" => "N",
                    "AJAX_OPTION_STYLE" => "Y",
                    "AJAX_OPTION_HISTORY" => "N",
                    "AJAX_OPTION_ADDITIONAL" => ""
                )
            );?>
            </div>
        </div>
    </div>
</div>

<script src="//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.6.0/highlight.min.js"></script>
<script src="/local/assets/js/swiper.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', (event) => {
        document.querySelectorAll('pre code').forEach((el) => {
            hljs.highlightElement(el);
        });

        swSliderMob = new Swiper('#project-slider-mob .swiper-container', {
            slidesPerView: 1,
            navigation: {
                nextEl: '#swiper-button-next-mob',
                prevEl: '#swiper-button-prev-mob'
            },
            pagination: {
                el: '.swiper-pagination-mob',
                type: 'bullets',
                clickable: true
            },
            // autoHeight: true
        });

        // set same height of title in slider

        
        function getHighestTitle() {
            let heighest = 0;
            const sliderTitles = document.querySelectorAll('.blog__also-title');
            sliderTitles.forEach(block => {
                let blockHeight = block.clientHeight;
                if (blockHeight > heighest) {
                    heighest = blockHeight;
                }
            });

            sliderTitles.forEach(block => {
                block.style.height = `${heighest}px`;
            });
        }
        
        getHighestTitle();
    });       
</script>


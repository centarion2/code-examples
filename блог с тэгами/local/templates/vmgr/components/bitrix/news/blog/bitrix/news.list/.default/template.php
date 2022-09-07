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
// debug($arParams);

$title = 'БЛОГ';
if (!empty($arResult["SECTION"]))
{
	$title .= ': '. $arResult["SECTION"]["PATH"][0]["NAME"];
}
?>

<div class="blog-meta">
    <div class="blog-meta__wrapper wrapper">
        <h1 class="common-h"><?= $title ?></h1>
    </div>
</div>

<div class="wrapper">
    <div class="blog__container">
        <div class="blog__list">
			<?foreach($arResult["ITEMS"] as $arItem):?>
				<?
				$this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
				$this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
				
				if (!empty($arResult["SECTION"])) {
					$detailPageUrl = '/blog/'. $arResult["SECTION"]["PATH"][0]["CODE"] . '/' . $arItem["CODE"] . '/';
				} else {
					$detailPageUrl = $arItem["DETAIL_PAGE_URL"];
				}
				?>
				<article class="blog__item" id="<?=$this->GetEditAreaId($arItem['ID']);?>">
					<div class="blog__item-image">
						<img src="<?=$arItem["PREVIEW_PICTURE"]["SRC"]?>"
						width="<?=$arItem["PREVIEW_PICTURE"]["WIDTH"]?>"
						height="<?=$arItem["PREVIEW_PICTURE"]["HEIGHT"]?>"
						alt="<?=$arItem["PREVIEW_PICTURE"]["ALT"]?>"
						title="<?=$arItem["PREVIEW_PICTURE"]["TITLE"]?>" 
						class="blog__image">
					</div>

					<div class="blog__item-info">
						<span class="blog__item-date"><?= $arItem["DISPLAY_ACTIVE_FROM"]?></span>
						<h4 class="blog__item-title"><?= $arItem["NAME"] ?></h4>
						<span class="blog__item-text"><?echo $arItem["PREVIEW_TEXT"];?></span>
						<div class="blog__tags">
							<? foreach ($arItem["SECTIONS"] as $arSection):?>
								<a href="/blog/<?= $arSection["CODE"] ?>/" class="blog__tags-item"><?= $arSection["NAME"] ?></a>
							<?endforeach;?>
						</div>

						<a href="<?= $detailPageUrl ?>" class="blog__item-link">Подробнее</a>
					</div>

					<a href="<?= $detailPageUrl ?>" class="blog__link"></a>
				</article>
			<?endforeach;?>
			<br /><?=$arResult["NAV_STRING"]?>
        </div>

        <div class="blog__sections">
            <h3 class="blog__sections-title">Все разделы</h3>

            <div class="blog__sections-list">
                <? foreach ($arResult["ALL_SECTIONS"] as $arSection): 
					$active = ($arParams["PARENT_SECTION_CODE"] == $arSection["CODE"]) ? "blog__sections-item--active" : "";
					?>
					<a href="/blog/<?= $arSection["CODE"] ?>/" class="blog__sections-item <?= $active ?>"><?= $arSection["NAME"] ?></a>
				<? endforeach; ?>
            </div>
        </div>
    </div>
</div>
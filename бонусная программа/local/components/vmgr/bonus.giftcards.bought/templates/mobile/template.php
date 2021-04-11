<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

if(!empty($arResult["ITEMS"])) {?>
    <div class="lk-bonus__item lk-bonus__item--last">
        <h2 class="lk-bonus__title">Ваши подарки</h2>
        <div class="lk-bonus__table lk-bonus__table--present">
            <?foreach($arResult["ITEMS"] as $arItem):?>
                <div class="lk-bonus__table-line">
                    <p class="lk-bonus__table-line-mob"><span>Дата</span><span><?=$arItem["DATE"]?></span></p>
                    <div class="lk-bonus__present-wrap">
                        <p><?=$arItem["CARD_TEXT"]?></p>
                        <img class="lk-bonus__present-img" src="<?=$arItem["PICTURE"]?>" alt="">
                    </div>
                    <p class="lk-bonus__table-line-mob"><span>Номинал</span><span><?=$arItem["NOMINAL"]?>  ₽</span</p>
                </div>
            <?endforeach?>
        </div>
        <?if(!$arResult["LAST"]) {?>
            <button id="load-bonus" class="btn-hover blue-btn round-btn bonus-load">Загрузить еще</button>
        <?}?>
    </div>
    <p>С полными правилами бонусной программы вы можете ознакомиться <a class="common-href" href="<?=$arResult["SETTINGS"]["FULL_RULES_PATH"]?>" target="_blank">по ссылке</a></p>
<?}?>
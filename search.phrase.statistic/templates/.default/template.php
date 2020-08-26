<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) exit('Prolog not included');
if(!empty($arResult["ITEMS"])) {?>
    <h2 class="content-search__h common-h1">Популярные запросы:</h2>
    <?foreach($arResult["ITEMS"] as $arItem) {?>
        <a style="color: #007bac;" href="/search/?q=<?=$arItem["DATA"]["PHRASE"]?>"><?=$arItem["DATA"]["PHRASE"]?> (<?=$arItem["DATA"]["RESULT_COUNT"]?>)</a> | 
    <?}
}?>

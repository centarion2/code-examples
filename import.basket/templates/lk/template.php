<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>

<?  // Кнопка и форма загрузки файла на сервер. Указывается максимальный размер файла ?>
    <div class="alert-msg__title">
        <span>Загружайте не более 500 артикулов в файле.</span>
        <span>Поддерживаемые форматы csv, xlsx .</span>
        <span><a href="/test.xlsx">Скачать пример файла</a> для загрузки</span>
    </div>
    <form enctype="multipart/form-data" method="POST">
        <!-- Поле MAX_FILE_SIZE должно быть указано до поля загрузки файла -->
        <input type="hidden" name="MAX_FILE_SIZE" value="200000" />
        <label class="file-label">
            <span class="file-btn">Выбрать файл</span>
            <span class="file-name">Файл не выбран</span>
            <input class="visually-hidden" accept=".csv, .xlsx, .xls" required name="file" type="file" />
        </label>
        <div class="frm-select" style="margin: 10px 0;">
            <input type="checkbox" name="clear" id="clear-excel" value="yes">
            <label for="clear-excel">Очистить корзину перед загрузкой?</label>
        </div>
        <div class="alert-bl-fl alert-bl-fl--last">
            <button type="submit">Загрузить</button>
        </div>
    </form>
<?
if($arResult) { 
    // Если файл был загружен выдается форма и результаты
    if(isset($arResult["ERROR"])) {
        // Есть ошибки при импорте выдается список ошибок
        ?><span class="msg-error--ab"><?=$arResult["ERROR"]?></span><?
    } 
    if(!empty($arResult["ITEMS"])) {
        // Если файл успешно загружен выдается статистика?>
        <div class="alert-msg--info active">
            Загружено товаров <?=$arResult["LOADED_COUNT"]?>/<?=$arResult["TOTAL_COUNT"]?><br>
            В наличии: <?=$arResult["IS_YES"]?><br>
            Уточняйте: <?=$arResult["IS_SPECIFY"]?><br>
            Отсутствует: <?=$arResult["IS_NO"]?><br>
            <span>Не загружено: <?=$arResult["ERROR_COUNT"]?>
            <?if($arResult["IS_LOG"] == "Y") {?>
                <a class="alert-btn" href="<?=$arResult["LOG_FILE"]?>" target="_blank">Посмотреть</a>
                <?}?>
            </span>
            <a class="go-to-basket" href="<?=$arParams["PATH_TO_BASKET"]?>">Перейти в корзину</a>
        </div>
        <?
    }
    

} else {
    // Если файл еще не был загружен то arResult равен null и выдается только форма
}
//echo '<PRE>';print_r($arResult);echo '</PRE>';111
?>
    
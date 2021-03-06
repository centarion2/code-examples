<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>

<?  // Кнопка и форма загрузки файла на сервер. Указывается максимальный размер файла ?>
    <div class="alert-msg__wrapper basket-excel__wrapper">
        <div class="alert-msg alert-msg--basket">
            <div class="alert-msg__title">
                <span>Загрузить корзину из Excel.</span>
                <span>Загружайте не более 500 артикулов в файле.</span>
                <span>Поддерживаемые форматы csv, xlsx . <a href="/test.xlsx">Скачать пример файла</a> для загрузки</span>
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
                    <button class="alert-btn alert-btn--close close-excel" type="button">Закрыть</button>
                    <button class="alert-btn" type="submit">Загрузить</button>
                </div>
            </form>
            <button class="close-excel close-info-style">
                <svg class="close-btn ico-btn" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 9 9">
                    <path d="M4 0h1v9H4V0zM0 4h9v1H0V4z"></path>
                </svg>
            </button>
        </div>

        <button class="basket-excel lk-btn--content">
            <span>загрузить товары из Excel</span>
            <svg class="order__btn-nav-ico ico-btn" viewBox="0 0 17.9 11.3"><path d="M17.9 5.7L12.2 0l-.7.7L16 5.2H0v1h16l-4.5 4.4.7.7 5.7-5.6z"></path></svg>
        </button>
    </div>
   
<?
if($arResult) { 
    // Если файл был загружен выдается форма и результаты
    if(isset($arResult["ERROR"])) {
        // Есть ошибки при импорте выдается список ошибок
        ?><span class="msg-error--ab"><?=$arResult["ERROR"]?></span><?
    } 
    if(!empty($arResult["ITEMS"])) {
        // Если файл успешно загружен выдается статистика?>
        <div class="alert-msg alert-msg--basket alert-msg--info active" style="min-width: 190px;">
            Загружено товаров <?=$arResult["LOADED_COUNT"]?>/<?=$arResult["TOTAL_COUNT"]?><br>
            В наличии: <?=$arResult["IS_YES"]?><br>
            Уточняйте: <?=$arResult["IS_SPECIFY"]?><br>
            Отсутствует: <?=$arResult["IS_NO"]?><br>
            <span>Не загружено: <?=$arResult["ERROR_COUNT"]?>
            <?if($arResult["IS_LOG"] == "Y") {?>
                <a href="<?=$arResult["LOG_FILE"]?>" target="_blank">Посмотреть</a>
            <?}?>
            </span>
            <button class="alert-btn alert-btn--full" onclick="location.reload();">Обновить корзину</button>
            <button class="close-info">
                <svg class="close-btn ico-btn" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 9 9">
                    <path d="M4 0h1v9H4V0zM0 4h9v1H0V4z"></path>
                </svg>
            </button>
        </div>
        <?
    }
    

} else {
    // Если файл еще не был загружен то arResult равен null и выдается только форма
}
//echo '<PRE>';print_r($arResult);echo '</PRE>';111
?>
    
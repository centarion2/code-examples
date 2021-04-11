<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) exit('Prolog not included');
?>
<div class="lk__content">
    <h1 class="lk-content__h1 common-h1">Каталог подарков</h1>
    <p class="lk-content__num-title">Вы можете выбрать подарки на сумму <span><?=$arResult["TOTAL_CREDIT"]?> ₽</span></p>
    <button class="round-btn load-prav">Показать правила оформления</button>
    <div class="prav-content">
        <ul>
            <?=$arResult["SETTINGS"]["RULES"]?>
        </ul>
    </div>
    <?if(!empty($arResult["ITEMS"])) {?>
        <div class="lk-bonus__present-filter">
            <p> </p>
        </div>
        <div class="lk-bonus__present-list" id="bounusList">
            <?foreach($arResult["ITEMS"] as $arItem):?>
                <div class="lk-bonus__present-item" bonus-id="<?=$arItem["UF_PRODUCT_ID"]?>">
                    <img src="<?=$arItem["PICTURE"]?>" alt="">
                    <p class="lk-bonus__present-title"><?=$arItem["NAME"]?></p>
                    <div class="lk-bonus__present-controls">
                        <div class="lk-bonus__present-price">
                            <span class="lk-bonus__present-price-title">Номинал</span>
                            <label>
                                <select class="lk-bonus__present-price-select">
                                    <?foreach($arItem["NOMINALS"] as $value):?>
                                        <option value="<?=$value["NOMINAL"]?>"><?=$value["NOMINAL_VALUE"]?> ₽</option>
                                    <?endforeach?>
                                </select>
                            </label>
                        </div>
                        <div class="lk-bonus__present-price-btngroup">
                            <button class="lk-bonus__present-btn lk-bonus__present-btn--more" bonus-id="<?=$arItem["UF_PRODUCT_ID"]?>">Подробнее</button>
                            <button class="lk-bonus__present-btn lk-bonus__present-btn--buy round-btn blue-btn" bonus-id="<?=$arItem["UF_PRODUCT_ID"]?>" bonus-name="<?=$arItem["NAME"]?>" <?=$arResult["BUY_DISABLED"]?'disabled':''?>>Выбрать</button>
                        </div>
                    </div>
                </div>
            <?endforeach?>
        </div>

    <?}?>
</div>

<div id="popup-buy-content" style="display: none;">
    <h3 class="popup-buy__title">Оформление сертификата</h3>
    <form class="popup-buy__form">
        <p>Получить сертификат</p>
        <label class="popup-buy__lab-bl check option">
            <input type="checkbox" class="check__input" checked>
            <span class="check__box"></span>
            <span>На почту <?=$arResult["EMAIL"]?></span>
        </label>
        <p>На номер <?=$arResult["PHONE"]?> отправлен код подтверждения. 
            Укажите код подтверждения из SMS, чтобы продолжить оформление сертификата</p>
        <label class="popup-buy__code">
            <b>Код подтверждения</b>
            <input class="popup-buy__code-inpt" name="code" type="text">
            <button class="reset-btn popup-buy__resend-btn" type="button">Выслать повторно<span></span></button>
        </label>
        <label class="popup-buy__policy">
            <input type="checkbox">
            <span>Согласен с условиями <a style="color: #007caa;" href="<?=$arResult["SETTINGS"]["FULL_RULES_PATH"]?>" target="_blank">бонусной программы</a></span>
        </label>
        <button class="round-btn blue-btn popup-buy__btn disable">Продолжить</button>
    </form>
</div>

<script>
    const dataBonus = {
        <?foreach($arResult["ITEMS"] as $arItem):
            // Текст для кнопки "подробнее"?>
        <?=$arItem["UF_PRODUCT_ID"]?>: `<?=$arItem["DESCRIPTION"]?><br>`,
        <?endforeach?>
    }
</script>

<script>
    // Функция запускает виджет оплаты и обрабатывает результат
    function executePayment(transactionId, productId, nominal, name, handler) {
        handler.close();
        handler.destroy();
        var widget = new cp.CloudPayments();
        widget.charge({ // options
                publicId: '',  //id из личного кабинета
                description: "Оплата доставки сертификата "+name +" (номинал " + nominal + ")", //назначение
                amount: <?=$arResult["SETTINGS"]["PRICE"]?>, //сумма
                currency: 'RUB', //валюта
                invoiceId: transactionId, //номер заказа  (необязательно)
                // accountId: 'user@example.com', //идентификатор плательщика (необязательно)
                data: {
                    "cloudPayments": {
                        "customerReceipt": <?=json_encode($arResult["CUSTOMER_RECEIPT"])?>
                    }
                }
            },
            function (options) {
                // При успешной оплате совершается покупка сертификата
                $.ajax({
                    type: "POST",
                    url: '/local/ajax/bp.php',
                    data: "action=success_pay&product_id="+productId+"&nominal="+nominal+"&transaction_id="+transactionId,
                    success: function(prmData){
                        prmData = JSON.parse(prmData);
                        if(prmData.status) {
                            // Случай 1 Успешная покупка
                            const successPopup =  new Popup(
                                "<div class='alert-wrap-bn'><img src='/local/templates/desktop/images/success.png' /><p>Сертификат в процессе оформления. Как только он будет готов, вы получите его на электронную почту.</p><a class='btn blue-btn round-btn' href='/personal/bonus/'>Закрыть</a></div>",
                                reloadPageMailBonus
                            );
                            successPopup.init();
                            successPopup.open(null,'');
                        } else {
                            // Случай 3. Оплата прошла, но сертификат не оформился
                            const successPopup =  new Popup(
                                "<div class='alert-wrap-bn'><img src='/local/templates/desktop/images/success.png' /><p>Сертификат в процессе оформления. Как только он будет готов, вы получите его на электронную почту. Оформление сертификата может занимать до 3-х дней.</p><a class='btn blue-btn round-btn' href='/personal/bonus/'>Закрыть</a></div>",
                                reloadPageMailBonus
                            );
                            successPopup.init();
                            successPopup.open(null,'');
                        }
                    }

                });
            },
            function (reason, options) {
                // Действие при неуспешной оплате (запись в лог)
                $.ajax({
                    type: "POST",
                    url: '/local/ajax/bp.php',
                    data: "action=error_pay&options=" + JSON.stringify(options) + '&reason=' + JSON.stringify(reason),
                    success: function(prmData){
                        const successPopup =  new Popup("<div class='alert-wrap-bn'><img src='/local/templates/desktop/images/fatal.png' /><p>Оформление сертификата прервано. Повторите попытку</p><a class='btn blue-btn round-btn' href='/personal/bonus/giftcards/'>Закрыть</a></div>", reloadPage);
                        successPopup.init();
                        successPopup.open(null,'');
                    }

                });
            }
        );
        return true;
	}

    function reloadPage() {
        location.reload();
    }

    function reloadPageMailBonus() {
        location.href ='/personal/bonus/'
    }
</script>
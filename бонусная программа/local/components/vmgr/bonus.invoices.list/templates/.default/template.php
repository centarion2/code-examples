<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) exit('Prolog not included');

\Bitrix\Main\Page\Asset::getInstance()->addJs("https://cdn.jsdelivr.net/npm/chart.js@2.8.0", true);
?>

<? if ($arResult["TOTAL"] > 0) { ?>
    <div class="lk-bonus__item">
        <h2 class="lk-bonus__title">Ваши бонусы</h2>

        <div class="lk-bonus__content">
            <div class="chart-container lk-bonus__canvas">
                <canvas id="total"></canvas>
                <div class="char-total"><?= CurrencyFormatNumber($arResult["TOTAL"], 'RUB') ?></div>
            </div>
            <div class="lk-bonus__char">
                <? // Одно число
                if ($arResult["TOTAL_NO_CREDIT"] == 0 && $arResult["WARNING_BONUS"] == 0) {
                    ?>
                    <p class="lk-bonus__char-val lk-bonus__char-val--ptimary"><?= CurrencyFormatNumber($arResult["TOTAL_CREDIT"], 'RUB') ?></p>
                    <p class="lk-bonus__char-title">Доступно сейчас</p>
                    <? // Три числа
                } elseif ($arResult["TOTAL_NO_CREDIT"] > 0 && $arResult["WARNING_BONUS"] > 0) {
                    ?>
                    <p class="lk-bonus__char-val lk-bonus__char-val--ptimary"><?= CurrencyFormatNumber($arResult["TOTAL_CREDIT"], 'RUB') ?></p>
                    <p class="lk-bonus__char-title">Доступно сейчас</p>
                    <p class="lk-bonus__char-val lk-bonus__char--second"><?= CurrencyFormatNumber($arResult["TOTAL_NO_CREDIT"], 'RUB') ?></p>
                    <p class="lk-bonus__char-title">Будет доступно с <?= $arResult["DATE_CREDIT"] ?></p>
                    <p class="lk-bonus__char-val lk-bonus__char--second warm-text"><?= CurrencyFormatNumber($arResult["WARNING_BONUS"], 'RUB') ?></p>
                    <p class="lk-bonus__char-title">Сгорят <?= $arResult["WARNING_DATE"] ?></p>
                    <? // Два числа
                } else {
                    ?>
                    <p class="lk-bonus__char-val lk-bonus__char-val--ptimary"><?= CurrencyFormatNumber($arResult["TOTAL_CREDIT"], 'RUB') ?></p>
                    <p class="lk-bonus__char-title">Доступно сейчас</p>
                    <? if ($arResult["TOTAL_NO_CREDIT"] > 0): ?>
                        <p class="lk-bonus__char-val lk-bonus__char--second"><?= CurrencyFormatNumber($arResult["TOTAL_NO_CREDIT"], 'RUB') ?></p>
                        <p class="lk-bonus__char-title">Будет доступно с <?= $arResult["DATE_CREDIT"] ?></p>
                    <? endif ?>
                    <? if ($arResult["WARNING_BONUS"] > 0): ?>
                        <p class="lk-bonus__char-val lk-bonus__char--second warm-text"><?= CurrencyFormatNumber($arResult["WARNING_BONUS"], 'RUB') ?></p>
                        <p class="lk-bonus__char-title">Сгорят <?= $arResult["WARNING_DATE"] ?></p>
                    <? endif ?>
                <? } ?>

            </div>
        </div>
    </div>
<? } else { ?>
    <div class="lk-bonus__item">
        <div class="lk-bonus__content">
            <div class="empty-bonus">
                Нет доступных бонусов
                <p style="font-size: 1rem; font-weight: normal; color: #000; padding: 15px 0; line-height: 1.4">
                    На вашем бонусном счете пока нет начисленных бонусов.
                    Для начисления бонусов вам необходимо
                    <b>подтвердить адрес электронной почты</b>, указанный в профиле контрагента в
                    разделе <a href="/personal/profiles/" style="color: #037caa">"Профили покупателя"</a> личного кабинета.
                     До момента подтверждения адреса электронной почты, информация о начислении бонусов будет недоступна.
                </p>
                <p style="font-size: 1rem; font-weight: normal; color: #000; padding: 15px 0; line-height: 1.4">
                    Если адрес электронной почты контрагента уже подтвержден, то в соответсвии с <a href="<?=$arResult["SETTINGS"]["FULL_RULES_PATH"]?>"
                                                                                            target="_blank" style="color: #037caa">правилами
                        бонусной программы</a><br>
                    бонусы будут начислены в течении 14 дней с момента оплаты заказа.
                </p>
                <a class="reset-btn round-btn blue-btn catalog-link__bonus" href="/personal/profiles/" style="width: auto; margin-right: 20px !important; display: inline-block !important;">Профили покупателя</a>
                <a class="reset-btn round-btn blue-btn catalog-link__bonus" href="/catalog/" style="display: inline-block !important;">Перейти в каталог</a>
            </div>
        </div>
    </div>
<? } ?>

<? if ($arResult["TOTAL_CREDIT"] > 0) { ?>
    <div class="lk-bonus__item">
        <h2 class="lk-bonus__title">Доступно для использования</h2>

        <div class="lk-bonus__avalib">
            <div class="lk-bonus__avalib-item">
                <p class="lk-bonus__char-val lk-bonus__char-val--ptimary"><?= CurrencyFormatNumber($arResult["TOTAL_CREDIT"], 'RUB') ?></p>

                <p class="lk-bonus__char-title">Вы можете выбрать подарки на эту сумму</p>
            </div>
            <!-- <div class="lk-bonus__avalib-item">
                <p class="lk-bonus__char-val lk-bonus__char--second">237</p>
                <p class="lk-bonus__char-title">Сгорят после 20.10.2020</p>
            </div>-->
            <div class="lk-bonus__avalib-btnwrap">
                <a href="<?= $arParams["PATH_TO_CATALOG"] ?>" class="lk-bonus__avalib-btn btn-hover blue-btn round-btn">Выбрать
                    подарки</a>
            </div>
        </div>
    </div>
<? } ?>

<? if (!empty($arResult["ITEMS"])) { ?>
    <div class="lk-bonus__item lk-bonus__item--last">
        <h2 class="lk-bonus__title">Детализация</h2>

        <div class="lk-bonus__table">
            <div class="lk-bonus__table-head lk-bonus__table-line">
                <span>№ Заказа</span>
                <span>Дата покупки</span>
                <span>Сумма</span>
                <span>Сумма к начислению</span>
                <span>Бонусы</span>
            </div>
            <?foreach ($arResult["ITEMS"] as $arItem) {

                // Статус бонусы доступны (зеленый цвет)
                if ($arItem["STATUS"] === 'available') {
                    $availableText = '<span class="bonus-table-date">' . CurrencyFormatNumber($arItem["BONUS_AVAILABLE"], 'RUB');
                    if (!empty($arItem["DATE_CANCEL"])) {
                        $availableText .= " <span>(до " . $arItem["DATE_CANCEL"] . ")</span>";
                    }
                    $availableText .= "</span>";
                    $statusClass = "bonus-baget--succ";
                    $title = "";
                    // Статус бонусы еще не доступны (оранжевый цвет)
                } elseif ($arItem["STATUS"] == 'not_available') {
                    $availableText = '<span class="bonus-table-date orange-text">Будет доступно с ' . $arItem["DATE_AVAILABLE"] . '</span>';
                    $statusClass = "bonus-baget--warm";
                    $title = '<div class="title-hint">';
                    $title .= "Бонусы будут доступны для использования с " . $arItem["DATE_AVAILABLE"];
                    $title .= '</div>';
                    // Статус бонусы скоро сгорят (красный цвет)
                } elseif ($arItem["STATUS"] == 'warning') {
                    $availableText = '<span class="bonus-table-date">' . CurrencyFormatNumber($arItem["BONUS_AVAILABLE"], 'RUB');
                    ' <span style="color:red;">до ' . $arItem["DATE_CANCEL"] . '</span></span>';
                    if (!empty($arItem["DATE_CANCEL"])) {
                        $availableText .= ' <span style="color:red;">(до ' . $arItem["DATE_CANCEL"] . ")</span>";
                    }
                    $availableText .= "</span>";
                    $statusClass = "bonus-baget--error";
                    $title = '<div class="title-hint">';
                    $title .= "Срок действия начисленных бонусов истекает " . $arItem["DATE_CANCEL"];
                    $title .= '</div>';
                    // Статус нет больше бонусов (серый цвет)
                } else {
                    $availableText = '<span class="bonus-table-date">' . CurrencyFormatNumber($arItem["BONUS_AVAILABLE"], 'RUB') . '</span>';
                    $statusClass = "bonus-baget--pending";
                    $title = "";
                }

                // Сумма в квадратике
                if ($arItem["STATUS"] == 'not_available') {
                    $quadroSum = CurrencyFormatNumber($arItem["BONUS_FULL"], 'RUB');
                } else {
                    $quadroSum = CurrencyFormatNumber($arItem["BONUS_AVAILABLE"], 'RUB');
                }

                ?>
                <div class="lk-bonus__table-line">
                    <span><?= $arItem["NAME"] ?></span>
                    <span><?= $arItem["DATE"] ?></span>
                    <span><?= CurrencyFormatNumber($arItem["SUM"], 'RUB') ?> ₽</span>
                    <span><?= CurrencyFormatNumber($arItem["SUM_CREDIT"], 'RUB') ?> ₽</span>
                    <span class="bonus-baget <?= $statusClass ?>"><?= $title ?> <?= $quadroSum ?></span>
                    <button class="bonus-table-btn">
                        <svg class="bonus-table-btn-ico" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256">
                            <path
                                d="M237.5 85L134.3 188.4a8 8 0 01-5.7 2.4 8 8 0 01-5.7-2.4L19.7 85.1a8 8 0 010-11.3l4.2-4.3a8 8 0 0111.3 0l93.4 93.4L222 69.5a8 8 0 0111.3 0l4.2 4.3a8 8 0 010 11.3z"></path>
                        </svg>
                    </button>
                    <div class="lk-bonus__table-detail">
                        <div class="lk-bonus__table-detail-bl lk-bonus__table-detail-bl--first">
                            <b class="lk-bonus__detail-title">Покупка</b>

                            <div class="lk-bonus__detail-item">
                                <span>Сумма накладной</span><span><?= CurrencyFormatNumber($arItem["SUM"], 'RUB') ?>
                                    ₽</span></div>
                            <div class="lk-bonus__detail-item">
                                <span>Сумма возврата</span><span><?= CurrencyFormatNumber($arItem["SUM_RETURN"], 'RUB') ?>
                                    ₽</span></div>
                        </div>
                        <div class="lk-bonus__table-detail-bl">
                            <b class="lk-bonus__detail-title">Бонусы</b>

                            <div class="lk-bonus__detail-item">
                                <span>Всего</span><span><?= CurrencyFormatNumber($arItem["BONUS_FULL"], 'RUB') ?></span>
                            </div>
                            <div class="lk-bonus__detail-item">
                                <span>Использовано</span><span><?= CurrencyFormatNumber($arItem["BONUS_SPENT"], 'RUB') ?></span>
                            </div>
                        </div>
                        <div class="lk-bonus__table-detail-bl--line"></div>
                        <div class="lk-bonus__table-detail-bl lk-bonus__table-detail-bl--first">
                            <div class="lk-bonus__detail-item">
                                <span>Сумма к начислению</span><span><?= CurrencyFormatNumber($arItem["SUM_CREDIT"], 'RUB') ?>
                                    ₽</span></div>

                        </div>
                        <div class="lk-bonus__table-detail-bl">
                            <div class="lk-bonus__detail-item"><span>Доступно</span><?= $availableText ?></div>
                        </div>
                    </div>
                </div>
            <? } ?>
        </div>
        <? if (!$arResult["LAST"]) { ?>
            <button id="load-orders" class="btn-hover blue-btn round-btn bonus-load">Загрузить еще</button>
        <? } ?>
    </div>
<? } ?>

<script>
    var ctx = document.getElementById('total').getContext('2d');
    var myDoughnutChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Доступно', 'Ожидание'],
            datasets: [{
                data: [<?=$arResult["TOTAL_CREDIT"]?>, <?=$arResult["TOTAL_NO_CREDIT"]?>],
                backgroundColor: [
                    '#037caa',
                    '#ffa600'
                ],
                borderWidth: 0
            }]
        },
        options: {
            legend: {
                display: false,
            },
            aspectRatio: 1,
            cutoutPercentage: 70
        },
    });
</script>
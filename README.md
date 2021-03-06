# Примеры кода

1 Компонент 1С:Битрикс Управление сайтом test_component представляет собой форму вычисления рабочего дня с пропуском праздничных дней и выходных.
Компонент интегрирован в систему.
Компонент имеет один шаблон по умолчанию.
Установка компонента:
  - скопируйте код компонента в папку local/components/centarion/,
  - найдите компонент в панели компонентов битрикса и перетащите на страницу,
  - сохраните страницу.

2 Компонент search.phrase.statistic, выводядит информацию о популярных поисковых запросах на сайте.
Степень популярности определяется параметром компонента COUNT_FROM.
Выводятся запросы, которые встретились больше или равно COUNT_FROM раз. Остальные пропускаются.
Компонент обращается к таблице базы данных b_search_phrase
Компонент не интегрирован в систему.
Компонент имеет один шаблон по умолчанию
Установка компонента:
  - скопируйте папку с компонентом по пути local/components/vmgr/,
  - скопируйте вызов (см. ниже) на страницу поиска, например /search/index.php,
  - настройте параметры компонента.
```
<? // Блок популярные запросы
$APPLICATION->IncludeComponent(
    "vmgr:search.phrase.statistic",
    "",
    Array(
        "COUNT_FROM" => 10, // Фильтр популярных элементов, фразы с количеством меньшим COUNT_FROM удаляются из выборки
        "PHRASE_COUNT" => 100, // Количество выводимых элементов
        "MIN_LENGTH" => 4 // Минимальная длина поисковой фразы
    )
);?>
```
3 Компонент для кнопки импорта товаров в корзину из Excel

Поддерживаются файлы формата csv и xlsx. Для загрузки последнего используется
свободнораспространяемая библиотека PHPExcel.
Существуют шаблоны для расположения компонента в корзине (basket) 
и в личном кабинете (lk) аналогичного функционирования. Также есть мобильная версия.
Установка компонента:
 - Скопировать код библиотеки PHPExcel в папку /local/reports/excel/ проекта.
 - Скопируйте папку с компонентом в папку /local/components/vmgr/,
 - Разместите код в корзине в личном кабинете вашего сайта, как указано ниже,
 - Создайте папку /upload/basketfromхls/ и выставите права на запись на нее.
```
<?// Кнопка импорта в корзину из Excel
$APPLICATION->IncludeComponent(
	"vmgr:import.basket",
	"basket",
	Array(
		"PATH_TO_BASKET" => '/cart/'
	),
false
);?> 
```

4 Бонусная программа

Содержит три компонента для вывода бонусной программы на странице личного кабинета 
пользователя, обработчик аякс запросов от них /ajax/bp.php, а также менеджер
бонусной программы /lib/vmgr/bonusprogram/bonusprogrammanager.php, управляющий всем
механизмом и содержащий все необходимые функции для ее функционирования. 
В проекте используется RESTful API к серверу "Карта подарков" для покупки сертификатов 
и виджет для оплаты сертификатов. Скриншоты прилагаются.

```
<? // Блоки по бонусам
$APPLICATION->IncludeComponent(
    "vmgr:bonus.invoices.list",
    "",
    Array(
        "PATH_TO_CATALOG" => '/personal/bonus/giftcards/',
    )
);?>

<? // Блок "Ваши подарки"
$APPLICATION->IncludeComponent(
    "vmgr:bonus.giftcards.bought",
    "",
    Array(
    )
);?>

<? // Страница "Каталог подарков"
$APPLICATION->IncludeComponent(
    "vmgr:bonus.giftcards.list",
    "",
    Array(
    )
);?>
```
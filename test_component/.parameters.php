<?php
/*
 * Файл bitrix/components/demo/catalog.element/.parameters.php
 */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();
$arComponentParameters = array(
    'GROUPS' => array(),
    'PARAMETERS' => array(
        'IBLOCK' => array(
            'PARENT' => 'BASE', // код группы, если отсутствует, подразумевается ADDITIONAL_SETTINGS
            'NAME' => 'Идентификатор инфоблока', // название параметра на текущем языке
            'TYPE' => 'STRING', // тип элемента управления, в котором будет устанавливаться параметр
            'MULTIPLE' => 'N',  // одиночное/множественное значение (N/Y)
            'DEFAULT' => '4', // значение по умолчанию
            'REFRESH' => 'Y',   // перегружать настройки или нет после выбора (N/Y)
        ),
        'PROPERTY_ID' => array(
            'PARENT' => 'BASE', // код группы, если отсутствует, подразумевается ADDITIONAL_SETTINGS
            'NAME' => 'Идентификатор свойства', // название параметра на текущем языке
            'TYPE' => 'STRING', // тип элемента управления, в котором будет устанавливаться параметр
            'MULTIPLE' => 'N',  // одиночное/множественное значение (N/Y)
            'DEFAULT' => '25', // значение по умолчанию
            'REFRESH' => 'Y',   // перегружать настройки или нет после выбора (N/Y)
        ),
    ),
);
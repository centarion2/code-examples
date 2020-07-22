<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();
$arComponentDescription = array(
    'NAME' => 'Мой компонент',
    'DESCRIPTION' => 'Тестовое задание',
    'PATH' => array(
        'ID' => 'demo',
        'NAME' => 'Мои компоненты',
        'CHILD' => array(
            'ID' => 'demo_catalog',
            'NAME' => 'Каталог товаров'
        )
    ),
    'ICON' => '/images/icon.gif'
);
<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) exit('Prolog not included');

$arComponentDescription = array(
    'NAME' => 'Страница подарков (VMGR)',
    'DESCRIPTION' => 'Выводит страницу с подарочными сертификатами (VMGR)',
    'PATH' => array(
        'ID' => 'vmgr',
        'CHILD' => array(
            'ID' => 'vmgr-bonus-giftcards-list',
            'NAME' => 'VMGR-Bonus-Giftcards-List'
        )
    ),
);

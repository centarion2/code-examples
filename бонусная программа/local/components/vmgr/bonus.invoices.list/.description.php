<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) exit('Prolog not included');

$arComponentDescription = array(
    'NAME' => 'Список накладных (VMGR)',
    'DESCRIPTION' => 'Выводит информацию о бонусах и накладных на основной странице бонусной программы "Текстэль" (VMGR)',
    'PATH' => array(
        'ID' => 'vmgr',
        'CHILD' => array(
            'ID' => 'vmgr-bonus-invoices-list',
            'NAME' => 'VMGR-Bonus-Invoices-List'
        )
    ),
);

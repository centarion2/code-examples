<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) exit('Prolog not included');

$arComponentDescription = array(
    'NAME' => 'Статистика по поиску (VMGR)',
    'DESCRIPTION' => 'Вывод актуальной статистики по поисковым фразам (VMGR)',
    'PATH' => array(
        'ID' => 'vmgr',
        'CHILD' => array(
            'ID' => 'vmgr-search-phrase-statistic',
            'NAME' => 'VMGR-Search-Phrase-Statistic'
        )
    ),
);

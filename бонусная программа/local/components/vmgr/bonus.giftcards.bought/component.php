<?
// Подготовительные операции
CModule::IncludeModule('iblock');
require_once $_SERVER['DOCUMENT_ROOT'] . "/local/lib/vmgr/bonusprogram/bonusprogrammanager.php";
$bp = Vmgr\BonusProgram\BonusProgramManager::get();
$pageSize = $bp::BP_LOAD_BONUS_PAGE_SIZE; /* По сколько элементов загружаем. Должно быть 5
                                            Должно быть такое же значение как переменная pageSize 
                                            в /local/ajax/bp.php action=load_bonus
                                            */
$arResult["SETTINGS"] = $bp->getSettingsArray(); // Загрузка настроек
$iblockId = $bp->getGiftCardsBoughtIblockId();
$iblockIdCards = $bp->getGiftCardsIblockId();
$userId = $USER->GetID();
//$userId = 1; // Тестовый юзер
$arSelect = [
    'PROPERTY_DATE',
    'PROPERTY_PRICE',
    'PROPERTY_CARD',
    'PROPERTY_CARD_TEXT',
    'PROPERTY_NOMINAL',
];
$arFilter = array(
    'IBLOCK_ID' => $iblockId,
    '=PROPERTY_USER' => $userId,
);
$res = \CIBlockElement::GetList(array('PROPERTY_DATE' => 'DESC'), $arFilter, false, ["iNumPage" => 1, "nPageSize" => $pageSize], $arSelect);
$arItems = [];
while($fields = $res->Fetch()) {
    $res2 = \CIBlockSection::GetByID($fields["PROPERTY_CARD_VALUE"]);
    $card = $res2->Fetch();
    $card_picture = CFile::GetPath($card["DETAIL_PICTURE"]);
    $arItems[] = [
        "DATE" => $fields["PROPERTY_DATE_VALUE"],
        "PRICE" => CurrencyFormatNumber($fields["PROPERTY_PRICE_VALUE"], "RUB"),
        "PICTURE" => $card_picture,
        "CARD_TEXT" => $fields["PROPERTY_CARD_TEXT_VALUE"],
        "NOMINAL" => CurrencyFormatNumber($fields["PROPERTY_NOMINAL_VALUE"], "RUB")
    ];
}
//debug( $userId, false, true);
$arResult["ITEMS"] = $arItems;
$arResult["LAST"] = $res->NavPageNomer == $res->NavPageCount;
$this->IncludeComponentTemplate();
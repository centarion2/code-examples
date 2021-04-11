<?php

namespace Vmgr\BonusProgram;

use \Vmgr\Core\Object\LoggableObject,
    \Vmgr\Iblock\IblockHelper;

use Bitrix\Main\Error;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Application;
use Bitrix\Main\Web\HttpClient;
use Bitrix\MessageService\Sender;
use Bitrix\MessageService\Sender\Result\SendMessage;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Loader; 
use Bitrix\Main\Entity;

// Настройки бонусной программы
require_once $_SERVER['DOCUMENT_ROOT'] . "/local/settings/bonusprogram.php";

/**
 * Менеджер бонусной программы
 * Пример:
require_once $_SERVER['DOCUMENT_ROOT'] . "/local/lib/vmgr/bonusprogram/bonusprogrammanager.php";
$bp = \Vmgr\BonusProgram\BonusProgramManager::get();
$bp->payCard($transactionId, $productId, $nominal);
 */
class BonusProgramManager extends LoggableObject
{
    private $iblockHelper;
    private $mailTo = ''; // Адрес почты для отчета. Если пустой письмо не отправляется, 
    private $returnTerm = 30; // Срок возврата по умолчанию 30 дней с даты покупки
    const BP_ERROR_MAIL_TO = 'm.bochkov@vmgr.ru,s.dmitriev@vmgr.ru'; // Отчет об ошибочных покупах отсылается по этому адресу(ам)
    const BP_SUCCESS_MAIL_TO = 'info@textelle.ru,m.bochkov@vmgr.ru,s.dmitriev@vmgr.ru'; // адресат письма о совершении покупки

    const IB_CODE_BP_RULE = 'vmgr.bonus_prg.rule';
    const IB_CODE_BP_INVOICE = 'vmgr.bonus_prg.invoice';
    const IB_CODE_BP_GIFT_CARDS = 'gift_cards'; // Код инфоблока "Подарочные сертификаты
    const IB_CODE_BP_GIFT_CARDS_BOUGHT = 'vmgr.bonus_prg.gift_cards_bought'; // Код инфоблока "Купленные подарки"
    const IB_CODE_BP_BONUS_EXPENSE = 'vmgr.bonus_prg.bonus_expense'; // Код инфоблока "Расход бонусов"
    const HLB_CODE_BONUS_DEBITING = 'BonusDebiting'; // Код highload блока
    const IB_CODE_BP_BONUS_SETTINGS = "vmgr.bonus_prg.settings"; // Код инфоблока "Настройки БП"
    const IB_CODE_BP_PAYMENT = "vmgr.bonus_prg.payment"; // Код инфоблока "Оплаты"
    
    const BP_LOAD_BONUS_PAGE_SIZE = 5; // По сколько купленных сертификатов загружаем (5)
    const BP_LOAD_ORDERS_PAGE_SIZE = 10; // По сколько накладных загружаем (10)
    
    const DEBUG = false; // Если true выводится отладочная информация
    
    private $giftCardsIblockId; // Инфоблок подарочных сертификатов
    private $ruleIblockId; // Инфоблок правил начисления бонусов
    private $invoiceIblockId; // Инфоблок накладных
    private $giftCardsBoughtIblockId; // Инфоблок "Купленные подарки"
    private $bonusExpenseIblockId; // Ид инфоблока "Расход бонусов"
    private $bonusSettingsIblockId; // Ид инфоблока "Текст"
    private $paymentIblockId; // Ид инфоблока "Оплаты"

    const BP_MAX_BUY = 3; // максимум одна покупка в день. Потом 3. Выставлять

    // Singleton
    private static $_object = null;
    private function __clone() {}
    public static function get() {
        if(is_null(self::$_object)) {
            self::$_object = new self();
        }
        return self::$_object;
    }

    private function __construct(Logger $prmLogger = null)
    {
        \CModule::IncludeModule("iblock");
        $this->iblockHelper = new IblockHelper();
        $this->giftCardsIblockId = $this->iblockHelper->getIblockIdByCode(self::IB_CODE_BP_GIFT_CARDS);
        if(!$this->giftCardsIblockId) {
            $this->giftCardsIblockId = 0;
        }
        $this->ruleIblockId = $this->iblockHelper->getIblockIdByCode(self::IB_CODE_BP_RULE);
        //echo "ruleIblockId: " . $this->ruleIblockId;
        if(!$this->ruleIblockId) {
            $this->ruleIblockId = 47;
        }
        $this->invoiceIblockId = $this->iblockHelper->getIblockIdByCode(self::IB_CODE_BP_INVOICE);
        //echo "invoiceIblockId: " . $this->invoiceIblockId;
        if(!$this->invoiceIblockId) {
            $this->invoiceIblockId = 46;
        }
        $this->giftCardsBoughtIblockId = $this->iblockHelper->getIblockIdByCode(self::IB_CODE_BP_GIFT_CARDS_BOUGHT);
        if(!$this->giftCardsBoughtIblockId) {
            $this->giftCardsBoughtIblockId = 0;
        }
        $this->bonusExpenseIblockId = $this->iblockHelper->getIblockIdByCode(self::IB_CODE_BP_BONUS_EXPENSE);
        if(!$this->bonusExpenseIblockId) {
            $this->bonusExpenseIblockId = 0;
        }
        $this->bonusSettingsIblockId = $this->iblockHelper->getIblockIdByCode(self::IB_CODE_BP_BONUS_SETTINGS);
        if(!$this->bonusSettingsIblockId) {
            $this->bonusSettingsIblockId = 0;
        }
        $this->paymentIblockId = $this->iblockHelper->getIblockIdByCode(self::IB_CODE_BP_PAYMENT);
        if(!$this->paymentIblockId) {
            $this->paymentIblockId = 0;
        } 
        parent::__construct($prmLogger);
    }

    // Функция задает электронную почту для отчетов
    public function setMail($mailTo) {
        $this->mailTo = $mailTo;
    }

    // Функция задает срок возврата по умолчанию
    public function setTerm($term) {
        $this->returnTerm = $term;
    }
    /**
     * Возвращает МАКСИМАЛЬНЫЙ бонусный процент, походящий под условия
     * @param $prmDate - дата
     * @param $prmUser - пользователь
     * @return int
     */
    public function getBonusPercent($prmDate, $prmUser)
    {
        global $USER;

        try{
            $dt = new \DateTime($prmDate);
        } catch (\Exception $e){
            $dt = new \DateTime();
        }
        $bonusTs = $dt->getTimestamp();

        $bonusUser = $prmUser;
        if(empty($bonusUser)){
            $bonusUser = $USER->getId();
        }

        $rsRules = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $this->iblockHelper->getIblockIdByCode(self::IB_CODE_BP_RULE)
            ],
            false,
            false,
            [
                'ID',
                'IBLOCK_ID',
                'NAME',
                'PROPERTY_DATE_START',
                'PROPERTY_DATE_END',
                'PROPERTY_USERS',
                'PROPERTY_BONUS_PERCENT'
            ]);

        $result = 0;
        while($arRule = $rsRules->fetch()){

            if(empty($arRule['PROPERTY_BONUS_PERCENT_VALUE']))
                continue;

            if(!is_array($arRule['PROPERTY_USERS_VALUE'])){
                $arRule['PROPERTY_USERS_VALUE'] = [
                    $arRule['PROPERTY_USERS_VALUE']
                ];
            }

            if(!empty($arRule['PROPERTY_DATE_START_VALUE'])) {
                $dt = new \DateTime($arRule['PROPERTY_DATE_START_VALUE']);
                $tsStart = $dt->getTimestamp();
            } else {
                $tsStart = 0;
            }

            if(!empty($arRule['PROPERTY_DATE_END_VALUE'])) {
                $dt = new \DateTime($arRule['PROPERTY_DATE_END_VALUE']);
                $tsEnd = $dt->getTimestamp();
            } else {
                $tsEnd = time() + 86400 * 365; // Прибавляем год к текущему времени
            }

            if(($bonusTs >= $tsStart) && ($bonusTs <= $tsEnd) &&
                (empty($arRule['PROPERTY_USERS_VALUE']) || in_array($bonusUser, $arRule['PROPERTY_USERS_VALUE'])) &&
                ($arRule['PROPERTY_BONUS_PERCENT_VALUE'] > $result)){
                $result = $arRule['PROPERTY_BONUS_PERCENT_VALUE'];
            }
        }

        return $result;
    }



    function invoiceHandler()
    {

    }

    // Функция очищает инфоблок "Подарочные сертификаты"
    public function CleanGiftCards () {
        if(!$this->giftCardsIblockId) return;

        $elementObject = new \CIBlockElement();

        $result = $elementObject->GetList
        (
            array("ID"=>"ASC"),
            array
            (
                '=IBLOCK_ID' => $this->giftCardsIblockId,
                'SECTION_ID' => 0,
                'INCLUDE_SUBSECTIONS' => 'N'
            )
        );
        while($element = $result->Fetch())
            $elementObject->Delete($element['ID']);

        $arFilter = Array('IBLOCK_ID'=>$this->giftCardsIblockId);
        $db_list = \CIBlockSection::GetList(Array(), $arFilter, true);
        while($ar_result = $db_list->GetNext())
        {//  удаление разделов
            \CIBlockSection::Delete($ar_result["ID"]);
        }
    }

    public function SyncGiftCards()
    {
        if(!$this->giftCardsIblockId) return false;

        $result = Array(
            "ADD" => Array(),
            "UPDATE" => Array()
        );
        $apiUrl = 'https://karta-podarkov.ru/rest/v1/products?apiKey=';
        $apiKey = '';
        $apiRequest = $apiUrl . $apiKey;

        $httpClient = new HttpClient();

        $arGiftCards = $httpClient->get($apiRequest);
        $arGiftCards = Json::decode($arGiftCards);

        $sectionObject = new \CIBlockSection();
        $elementObject = new \CIBlockElement();

        $counter["ADD"] = 0;
        $counter["UPDATE"] = 0;
        $counter["DELETE"] = 0;
        foreach ($arGiftCards as $giftCard)
        {
            // Проверка есть ли карта в базе
            $arExisting = $sectionObject->GetList(array('ID' => 'ASC'), array('IBLOCK_ID' => $this->giftCardsIblockId, '=UF_PRODUCT_ID' => $giftCard['product_id']), false, array('ID', 'UF_PRODUCT_ID', 'DETAIL_PICTURE'))->Fetch();
            $giftCardId = IntVal($arExisting["ID"]);

            // Подготовка параметров
            $arProps['UF_PRODUCT_ID'] = $giftCard['product_id'];
            $arProps['UF_URL'] = $giftCard['url'];
            $arProps['UF_EXPIRY_DATE'] = $giftCard['expiry_date'];
            $arProps['UF_INSTRUCTION'] = $giftCard['instruction'];
            $arSectionFields = array(
                'IBLOCK_ID' => $this->giftCardsIblockId,
                'NAME' => $giftCard['name'],
                'DESCRIPTION' => $giftCard['description'],
                'DETAIL_PICTURE' => \CFile::MakeFileArray($giftCard['image']),
            );
            $arSectionFields = array_merge($arSectionFields, $arProps);
            
            if ($giftCardId > 0)
            {
                // Обновление существующего сертификата
                if(!$sectionObject->Update($giftCardId, $arSectionFields)) {
                    // Если не обновляется, то обновляется без картинки
                    $arSectionFields['DETAIL_PICTURE'] = null;
                    $sectionObject->Update($giftCardId, $arSectionFields);
                } else {
                    // Если успешно обновилась картинка, то старая удаляется
                    \CFile::Delete($arExisting['DETAIL_PICTURE']);
                }
                $arActualSectionIDs[] = $giftCardId;
                if(self::DEBUG) echo "<br>".$giftCard['name']. ' обновлен';
                $result["UPDATE"][] = $giftCard['name'];
                $counter["UPDATE"]++;
            }
            else {
                // Добавление нового сертификата
                $arSectionFields['ACTIVE'] = 'N';
                $giftCardId = $sectionObject->Add($arSectionFields);
                // Если не добавляется, то добавляется без картинки
                if(!$giftCardId) {
                    $arSectionFields['DETAIL_PICTURE'] = null;
                    $giftCardId = $sectionObject->Add($arSectionFields);
                }
                $arActualSectionIDs[] = $giftCardId;
                if(self::DEBUG) echo "<br>".$giftCard['name']. ' создан';
                $result["ADD"][] = $giftCard['name'];
                $counter["ADD"]++;
            }
            // Добавление номиналов
            foreach ($giftCard['nominals'] as $nominal) {
                // Проверяем есть ли номинал в базе
                $nominalId = $elementObject->GetList(array('ID' => 'ASC'), array('IBLOCK_ID' => $this->giftCardsIblockId, '=IBLOCK_SECTION_ID' => $giftCardId, '=NAME' => $nominal['nominal']), false, false, array('ID', 'NAME'))->Fetch()['ID'];
                // Подготовка параметров
                $arElementFields = array(
                    'IBLOCK_ID' => $this->giftCardsIblockId,
                    'NAME' => $nominal['nominal'],
                    'IBLOCK_SECTION_ID' => $giftCardId,
                    'PROPERTY_VALUES' => array('PRICE' => $nominal['price'])
                );
                if($nominalId > 0) {
                    // Обновление цены существующего номинала
                    $elementObject->Update($nominalId, $arElementFields);
                    $arActualElementIDs[] = $nominalId;
                    if(self::DEBUG) echo "<br>Номинал ".$nominal['nominal'].' для '.$giftCard['name']." обновлен";
                } else {
                    // Добавление нового номинала
                    $arElementFields['ACTIVE'] = 'N';
                    $nominalId = $elementObject->Add($arElementFields);
                    $arActualElementIDs[] = $nominalId;
                    if(self::DEBUG) echo "<br>Номинал ".$nominal['nominal'].' для '.$giftCard['name'].' создан';
                }
            }
            // Удаление ненужных номиналов
            $obElements = $elementObject->GetList(array(), array('=IBLOCK_ID' => $this->giftCardsIblockId,  '=IBLOCK_SECTION_ID' => $giftCardId, '!ID' => $arActualElementIDs), false, array('ID'));
            while ($arElement = $obElements->Fetch())
            {
                $elementObject->Delete($arElement['ID']);
            }
            //if ($i++ > 1) break;
        }

        // Удаление ненужных сертификатов
        $obSections = $sectionObject->GetList(array(), array('=IBLOCK_ID' => $this->giftCardsIblockId, '!ID' => $arActualSectionIDs), false, false, array('ID'));
        while ($arSection = $obSections->Fetch())
        {
            $sectionObject->Delete($arSection['ID']);
            $counter["DELETE"]++;
        }

        // Формирование письма
        $content = "Отчет по синхронизации подарочных сертификатов\r\n";
        
        if(!empty($result["ADD"])) {
            $content .= "\r\nДобавлены следующие сертификаты:\r\n\r\n";
            foreach($result["ADD"] as $strValue) {
                $content .= $strValue;
                $content .= ", ";
            }
            $content = substr_replace($content,'.',-2);
        }
        
        if(!empty($result["UPDATE"])) {
            $content .= "\r\nОбновлены следующие сертификаты:\r\n\r\n";
            foreach($result["UPDATE"] as $strValue) {
                $content .= $strValue;
                $content .= ", ";
            }
            $content = substr_replace($content,'.',-2);
        }
        $content .= "\r\n\r\nИтого: добавлено {$counter["ADD"]}, обновлено {$counter["UPDATE"]}, удалено {$counter["DELETE"]}.\r\n";
        $mailheaders = "Content-type: text/plain; charset=utf-8 \r\n";
        if(!empty($this->mailTo))
            mail($this->mailTo, 'Отчет', $content, $mailheaders);

        return $counter;
    }
    
    // Функция генерирует и отображает правильный json для функции importInvoices
    public function GenerateTestJson() {
        $json = [ "Бонус" => [ 
            [
            "USER_ID" => 1,
            "DOCUMENT" => "00011",
            "DATE" => "10.10.2020", 
            "DATE_RETURN" => "15.10.2020", 
            "AMOUNT" => 100000.00, 
            "RETURN" => 0,
            "BONUS_PERCENT" => 2,
            "FORCE" => false,
            "AGENT" => 1
            ],
            [
                "USER_ID" => 1,
                "DOCUMENT" => "00011",
                "DATE" => "05.10.2020", 
                "DATE_RETURN" => "10.10.2020", 
                "AMOUNT" => 100000.00, 
                "RETURN" => 0,
                "BONUS_PERCENT" => 2,
                "FORCE" => false,
                "AGENT" => 1
            ],
            [
                "USER_ID" => 1,
                "DOCUMENT" => "00012",
                "DATE" => "10.10.2020", 
                "DATE_RETURN" => "15.10.2020", 
                "AMOUNT" => 250000.00, 
                "RETURN" => 12000.00,
                "BONUS_PERCENT" => 0,
                "FORCE" => false,
                "AGENT" => 1
            ],
            [
                "USER_ID" => 3,
                "DOCUMENT" => "00013",
                "DATE" => "10.10.2020", 
                "DATE_RETURN" => "", 
                "AMOUNT" => 30000.00, 
                "RETURN" => 2500.00,
                "BONUS_PERCENT" => 3,
                "FORCE" => false,
                "AGENT" => 1
            ],
        ]];
        
        echo json_encode($json);
    }

    // Функция импортирует накладные в инфоблок из json
    public function importInvoices($prmJson) {
        if(strlen(trim($prmJson)) == 0) return false; 
        $arData = json_decode($prmJson, true);
        \Bitrix\Main\Loader::includeModule('iblock');
        $elementObject = new \CIBlockElement();
        $counter = array(
            "ADD" => 0,
            "UPDATE" => 0,
            "IGNORE" => 0
        );
        // Цикл по записям (накладным)
        foreach($arData as $obItem) {
            $arItem = (array) $obItem;
            // Если накладная до 1 января 2021, то пропускаем
            if (strtotime($arItem["DATE"]) < strtotime("01.01.2021")) continue;
            
            // Игнорим юзера из 1C, поиск юзера по профилю по контрагенту
            $arItem["USER_ID"] = $this->getUserByAgent($arItem["AGENT"]);
            $arItem["USER_ID"] = intval($arItem["USER_ID"]);
            $arItem["DOCUMENT"] = trim($arItem["DOCUMENT"]);
            $arItem["DATE"] = $arItem["DATE"];
            // Корректировка даты возврата
            if(empty($arItem["DATE_RETURN"])) {
                // +30 дней к дате покупки
                $offset = strval($this->returnTerm).' day';
                $arItem["DATE_RETURN"] = (new \DateTime($arItem["DATE"]))
                    ->modify($offset)
                    ->format('d.m.Y');
            }
            $arItem["AMOUNT"] = floatval($arItem["AMOUNT"]);
            $arItem["RETURN"] = floatval($arItem["RETURN"]);
            $arItem["BONUS_PERCENT"] = floatval($arItem["BONUS_PERCENT"]);

            // Подготовка параметров инфоблока
            $arElementFields = array(
                'IBLOCK_ID' => $this->invoiceIblockId,
                'NAME' => $arItem["DOCUMENT"],
                'PROPERTY_VALUES' => array(
                    'USER' => $arItem["USER_ID"],
                    'INVOICE' => $arItem["DOCUMENT"],
                    'INVOICE_DATE' => DateTime::createFromPhp(new \DateTime($arItem["DATE"])),
                    'RETURN_DATE' => DateTime::createFromPhp(new \DateTime($arItem["DATE_RETURN"])),
                    'INVOICE_AMOUNT' => $arItem["AMOUNT"],
                    'RETURN_AMOUNT' => $arItem["RETURN"],
                    'BONUS_PERCENT_ORIGINAL' => floatval($arItem["BONUS_PERCENT"]),
                    'CONTRAGENT' => $arItem["AGENT"],
                )
            );
            $arElementFields['ACTIVE'] = 'Y';

            // Начисление бонусов
            $arElementFields['PROPERTY_VALUES']["BONUS_PERCENT"] = max($arItem["BONUS_PERCENT"], $this->getBonusPercent($arItem["DATE"], $arItem["USER_ID"]));
            //echo $bonusPercent.'%';
            $arElementFields['PROPERTY_VALUES']['BONUS_FULL'] = round(($arItem["AMOUNT"] - $arItem["RETURN"]) * $arElementFields['PROPERTY_VALUES']["BONUS_PERCENT"] / 100);
            $arElementFields['PROPERTY_VALUES']['BONUS_SPENT'] = 0; // Изначально бонусы невозможно потратить
            $arElementFields['PROPERTY_VALUES']['BONUS_AVAILABLE'] = 0; // Изначально бонусы не доступны
            // Проверяем есть ли уже в базе накладная, получаем данные
            $arSelect = [
                'ID', 
                'NAME',
                'PROPERTY_USER',
                'PROPERTY_INVOICE',
                'PROPERTY_INVOICE_DATE',
                'PROPERTY_RETURN_DATE',
                'PROPERTY_INVOICE_AMOUNT',
                'PROPERTY_RETURN_AMOUNT',
                'PROPERTY_BONUS_PERCENT_ORIGINAL',
                'PROPERTY_BONUS_PERCENT',
                'PROPERTY_BONUS_SPENT',
                'PROPERTY_BONUS_AVAILABLE',
                'PROPERTY_CONTRAGENT'
            ];
            $arInvoice = $elementObject->GetList(array('ID' => 'ASC'), array('IBLOCK_ID' => $iblockId, '=NAME' => $arItem["DOCUMENT"]), false, false, $arSelect)->Fetch();
            if($arInvoice) {  
                
                // Если ничего не изменилось накладная игнорируется
                if($arInvoice['PROPERTY_USER_VALUE'] == $arItem["USER_ID"] 
                    && $arInvoice['PROPERTY_INVOICE_VALUE'] == $arItem["DOCUMENT"]
                    && $arInvoice['PROPERTY_INVOICE_DATE_VALUE'] == $arItem["DATE"]
                    && $arInvoice['PROPERTY_RETURN_DATE_VALUE'] == $arItem["DATE_RETURN"]
                    && $arInvoice['PROPERTY_INVOICE_AMOUNT_VALUE'] == $arItem["AMOUNT"]
                    && $arInvoice['PROPERTY_RETURN_AMOUNT_VALUE'] == $arItem["RETURN"]
                    && $arInvoice['PROPERTY_BONUS_PERCENT_ORIGINAL_VALUE'] == $arItem["BONUS_PERCENT"]
                    && $arInvoice['PROPERTY_CONTRAGENT_VALUE'] == $arItem["AGENT"])
                {
                    if(self::DEBUG) echo 'Одинаковый'.$arInvoice["NAME"]."\r\n";
                    $counter["IGNORE"]++;
                    continue;
                } 

                //$arItem["FORCE"] = true; // для полной выгрузки накладных включается FORCE
                
                // Если дата возврата уже прошла накладная игнорируется
                if($this->compareDate($arItem["DATE_RETURN"]) && !$arItem["FORCE"]) {
                    if(self::DEBUG) echo 'Не подошла дата '.$arInvoice["NAME"]."\r\n";
                    $counter["IGNORE"]++;
                    continue;
                }
                // Форс-мажор, перерасчет бонусов по старой накладной VIP клиентам
                if($arItem["FORCE"] && $this->compareDate($arItem["DATE_RETURN"])) {
                    $arElementFields['PROPERTY_VALUES']['BONUS_AVAILABLE'] = $arElementFields['PROPERTY_VALUES']['BONUS_FULL'] - $arInvoice['PROPERTY_BONUS_SPENT_VALUE'];
                    $arElementFields['PROPERTY_VALUES']['BONUS_SPENT'] = $arInvoice['PROPERTY_BONUS_SPENT_VALUE'];
                } 
                
                // Обновление накладной
                $elementObject->Update($arInvoice["ID"], $arElementFields);
                if(self::DEBUG) echo "Обновление".$arInvoice["NAME"]."\r\n";
                $counter["UPDATE"]++;
            } else {
                // Добавление накладной
                $invoiceId = $elementObject->Add($arElementFields);
                if(self::DEBUG) echo "Добавление ".$arItem["DOCUMENT"]."\r\n";
                $counter["ADD"]++;
            }
        }
        if(self::DEBUG) echo 'Импорт завершен';
        $this->recalculateBonuses();
        if(self::DEBUG) echo 'Перерасчет бонусов';
        return $counter;
    }

    // Получение ид инфоблоков бонусной программы
    public function getGiftCardsIblockId() {
        return $this->giftCardsIblockId; // Инфоблок подарочных сертификатов
    }
    
    public function getRuleIblockId() {
        return $this->ruleIblockId; // Инфоблок правил начисления бонусов
    }
    
    public function getInvoiceIblockId() {
        return $this->invoiceIblockId; // Инфоблок накладных
    }
    
    public function getGiftCardsBoughtIblockId() {
        return $this->giftCardsBoughtIblockId; // Инфоблок купленных подарков
    }
    
    public function getBonusExpenseIblockId() {
        return $this->bonusExpenseIblockId; // Инфоблок купленных подарков
    }

    public function getBonusSettingsIblockId() {
        return $this->bonusSettingsIblockId; // Инфоблок "Текст"
    }

    public function getPaymentIblockId() {
        return $this->paymentIblockId; // Инфоблок "Текст"
    }

    // Возвращает true если дата возврата уже прошла (date в формате ДД.ММ.ГГГГ)
    public function compareDate($date) {
        $returnDate = new \DateTime($date);
        $todayDate = new \DateTime();
        //$returnToday = $todayDate->format("d.m.Y") == $date;
        $interval = intval(date_diff($todayDate, $returnDate)->format('%R%a'));
        return ($interval < 0);// || $returnToday);
    }

    // Функция совершает покупку подарочного сертификата
    // Возвращает true в случае успеха
    public function buyCard($transactionId, $productId, $nominal) {
        // Данные пользователя
        global $USER;
        $userId = $USER->GetID();
        $arUser = \CUser::GetByID($userId)->Fetch();
        $name = $arUser["NAME"];
        $email = trim($arUser["EMAIL"]);
        $phone = \Bitrix\Main\UserPhoneAuthTable::normalizePhoneNumber($arUser['PERSONAL_PHONE']);
        
        // Параметры
        $productId = intval($productId);
        if($productId <= 0) {
            if(self::DEBUG) echo 'Неправильный номер сертификата';
            $this->sendTextMail('Неправильный номер сертификата '.$productId);
            return false;
        }
        $nominal = intval($nominal);
        if($nominal <= 0) {
            if(self::DEBUG) echo 'Неправильный номинал сертификата';
            $this->sendTextMail('Неправильный номинал сертификата '.$productId." (номинал ".$nominal.")");
            return false;
        }
        if (strlen($transactionId) == 0) {
            if(self::DEBUG) echo 'Нет идентификатора транзакции';
            $this->sendTextMail('Нет идентификатора транзакции. '.$productId." (номинал ".$nominal.")");
            return false;
        }

        // Проверка можно ли покупать сертификат
        $iblockId = $this->getGiftCardsIblockId();
        $arFilter = array(
            'IBLOCK_ID' => $iblockId,
            "=UF_PRODUCT_ID" => $productId,
            );
		$res = \CIBlockSection::GetList(array('SORT' => 'ASC'), $arFilter, false, ["ID", "NAME"]);
		if($fields = $res->Fetch()) {
            $cardId = intval($fields["ID"]);
            $cardName = $fields["NAME"]; // Название сертификата, чтобы записать потом в купленные подарки
            if(self::DEBUG) echo 'Ид раздела (сертификата) '.$productId;
		} else {
            if(self::DEBUG) echo 'Нет такого сертификата в базе';
            $this->sendTextMail('Нет такого сертификата в базе '.$productId);
            return false;
        }
        $iblockId = $this->getGiftCardsIblockId();
        $arFilter = array(
            'IBLOCK_ID' => $iblockId,
            "IBLOCK_SECTION_ID" => $cardId,
            "=NAME" => $nominal
            );
		$res = \CIBlockElement::GetList(array('SORT' => 'ASC'), $arFilter, false, false, ["ID", "NAME", "PROPERTY_PRICE"]);
		if($fields = $res->Fetch()) {
            //$price = floatval($fields["PROPERTY_PRICE_VALUE"]);
            $price = intval($nominal);
            if(self::DEBUG) echo 'Цена '.$price;
		} else {
            if(self::DEBUG) echo 'Ошибка: нет такого номинала у данного сертификата';
            $this->sendTextMail('Ошибка: нет такого номинала у данного сертификата '.$cardName." ".$nominal);
            return false;
        }

        if($this->GetAvailableBonusTotal() < $price) {
            // Выход без покупки в случае если не хватает бонусов
            if(self::DEBUG) echo 'Ошибка: не хватает бонусов для покупки '.$cardName." ".$nominal.". Возможен взлом";
            $this->sendTextMail('Ошибка: не хватает бонусов для покупки '.$cardName." ".$nominal.". Возможен взлом");
            return false;
        }

        // Проверка максимум 1 покупка в день для одного юзера. Такая же проверка в ajax bp.php
        $iblockId = $this->getGiftCardsBoughtIblockId();
        $bought = [];
        $todayBuyCount = 0;
        $arFilter = array(
            'IBLOCK_ID' => $iblockId,
            'PROPERTY_USER' => $userId,
        );
		$res = \CIBlockElement::GetList(array('SORT' => 'ASC'), $arFilter, false, false, ["ID", "NAME", "PROPERTY_DATE"]);
		while($fields = $res->Fetch()) {
            // Собираются все сегодняшние покупки юзера
            $returnDate = new \DateTime($fields["PROPERTY_DATE_VALUE"]);
            $todayDate = new \DateTime();
            if ($todayDate->format("d.m.Y") == $returnDate->format("d.m.Y")) {
                $bought[] = $fields;
                $todayBuyCount++;
            }
        }

        if(self::DEBUG) echo "Кол-во покупок сегодня $todayBuyCount.";

        if ($todayBuyCount >= self::BP_MAX_BUY) {
            
            if(self::DEBUG) echo 'Ошибка: больше одной покупки в день. Возможен взлом';
            $this->sendTextMail('Ошибка: больше одной покупки в день. Возможен взлом');
            return false;
        }

        // Запись в базу о покупке сертификата (в STATUS будет статус операции)
        $iblock = $this->getBonusExpenseIblockId();
        if(self::DEBUG) echo 'Инфоблок "Расход бонусов" '.$iblock;
        $curDate = new DateTime();
        //$userId = 1; // Тестовый юзер
        $arExpenseElementFields = array(
            'IBLOCK_ID' => $iblock,
            'NAME' => $name.' '.$curDate->format("d.m.Y"),
            'ACTIVE' => 'Y',
            'PROPERTY_VALUES' => array(
                'DATE' => $curDate,
                'CARD' => $cardId,
                'NOMINAL' => $nominal,
                'AMOUNT' => $price,
                'USER_PAY' => $this->getSettingsArray()["PRICE"], // Сколько заплатил покупатель
                'TRANSACTION_ID' => $transactionId,
                'USER_ID' => $userId,
                'STATUS' => 'Выполнение запроса к подаркам'
            )
        );
        $elementObject = new \CIBLockElement();
        $recordId = $elementObject->Add($arExpenseElementFields);
        if(self::DEBUG) echo 'Добавлена запись '.$recordId;

        // Тело запроса
        $postData =[
            "fullname" => $name,
            "phone" => "0",
            "email" => $email,
            "products" => [
                [
                    "product_id" => intval($productId),
                    "nominal" => intval($nominal),
                    "quantity" => "1"
                ]
            ]
        ];

        // Запрос к подаркам
        if(!BP_LOCK_BUY_CARD) {
            $apiUrl = 'https://karta-podarkov.ru/rest/v1/create?apiKey='; 
            $apiKey = '';
            $apiRequest = $apiUrl . $apiKey;
            $httpClient = new HttpClient();
            $arResult = $httpClient->post($apiRequest, $postData);
        } else {
            // Сообщение об ошибке в статус для повторного запроса вручную
            $errorMsg = "Ошибка запроса к url https://karta-podarkov.ru/rest/v1/create. Статус: запрос заблокирован сайтом. Параметры запроса: ".print_r($postData, true);
        }

        // Обработка ответа от сервера подарков
        try {
            $arResult = Json::decode($arResult);
            if($arResult["status"] == 'ok') {
                if(self::DEBUG) echo "Запрос к подаркам успешно выполнен";
                // Запись идентификаторов, приходящих из карт подарков в журнал
                file_put_contents($_SERVER["DOCUMENT_ROOT"]."/kartapodarkov.txt", PHP_EOL.$arResult["orderId"], FILE_APPEND);
                $resultFlag = true;
            } elseif($arResult["status"] == 'error') {
                //echo "Ошибка: ".$arResult["message"];
                // Смена статуса операции
                $errorMsg .= "Ответ: ".print_r($arResult["message"], true);
                if(self::DEBUG) echo "Ошибка запроса к подаркам";
                \CIBlockElement::SetPropertyValueCode($recordId, "STATUS", $errorMsg); 
                $this->sendMail($recordId);
                $resultFlag = false; // Если истина, то возвращается ошибка в случае успеха
            } else {
                if(self::DEBUG) echo "Ошибка запроса к подаркам";
                \CIBlockElement::SetPropertyValueCode($recordId, "STATUS", $errorMsg); 
                $this->sendMail($recordId);
                $resultFlag = false;
            }
        } catch(\Exception $e) {
            // Смена статуса операции
            if(self::DEBUG) echo 'Ошибка запроса к подаркам';
            \CIBlockElement::SetPropertyValueCode($recordId, "STATUS", $errorMsg); 
            $this->sendMail($recordId);
            $resultFlag = false;
        }

        // Смена статуса операции
        \CIBlockElement::SetPropertyValueCode($recordId, "STATUS", 'Списание бонусов');      

        // Списание price баллов
        global $DB;
        $DB->StartTransaction(); // Начало транзакции
        try
        {
            // Загрузка накладных пользователя
            $iblockId = $this->getInvoiceIblockId();
            if(self::DEBUG) echo 'Инфоблок "Накладные" '.$iblockId;
            $arOrder = array(
                'PROPERTY_INVOICE_DATE' => 'ASC'
            );
            $arFilter = array(
                'IBLOCK_ID' => $iblockId,  
                '=PROPERTY_USER' => $userId, 
                '>PROPERTY_BONUS_AVAILABLE' => 0
            );
            $arSelect = array(
                'ID', 
                'IBLOCK_ID',
                'NAME',
                'PROPERTY_USER',
                'PROPERTY_INVOICE',
                'PROPERTY_INVOICE_DATE',
                'PROPERTY_RETURN_DATE',
                'PROPERTY_INVOICE_AMOUNT',
                'PROPERTY_RETURN_AMOUNT',
                'PROPERTY_BONUS_PERCENT_ORIGINAL',
                'PROPERTY_BONUS_PERCENT',
                'PROPERTY_BONUS_FULL',
                'PROPERTY_BONUS_SPENT',
                'PROPERTY_BONUS_AVAILABLE',
                'PROPERTY_CONTRAGENT'
            );
            $rsInvoices = \CIBlockElement::GetList($arOrder, $arFilter, false, false, $arSelect);
            $arInvoices = [];
            while($arInvoice = $rsInvoices->Fetch()) {
                $arInvoices[] = [
                    'ID' => intval($arInvoice['ID']),
                    'IBLOCK_ID' => intval($arInvoice['IBLOCK_ID']),
                    'NAME' => $arInvoice['NAME'],
                    'USER' => intval($arInvoice['PROPERTY_USER_VALUE']),
                    'INVOICE' => $arInvoice['PROPERTY_INVOICE_VALUE'],
                    'INVOICE_DATE' => DateTime::createFromPhp(new \DateTime($arInvoice['PROPERTY_INVOICE_DATE_VALUE'])),
                    'RETURN_DATE' => DateTime::createFromPhp(new \DateTime($arInvoice['PROPERTY_RETURN_DATE_VALUE'])),
                    'INVOICE_AMOUNT' => floatval($arInvoice['PROPERTY_INVOICE_AMOUNT_VALUE']),
                    'RETURN_AMOUNT' => floatval($arInvoice['PROPERTY_RETURN_AMOUNT_VALUE']),
                    'BONUS_PERCENT_ORIGINAL' => floatval($arInvoice['PROPERTY_BONUS_PERCENT_ORIGINAL_VALUE']),
                    'BONUS_PERCENT' => floatval($arInvoice['PROPERTY_BONUS_PERCENT_VALUE']),
                    'BONUS_FULL' => intval($arInvoice['PROPERTY_BONUS_FULL_VALUE']),
                    'BONUS_SPENT' => intval($arInvoice['PROPERTY_BONUS_SPENT_VALUE']),
                    'BONUS_AVAILABLE' => intval($arInvoice['PROPERTY_BONUS_AVAILABLE_VALUE']),
                    'CONTRAGENT' => $arInvoice['PROPERTY_CONTRAGENT_VALUE']
                ];
            }

            if(empty($arInvoices)) {
                throw new \Exception('Нет доступных накладных');
            }
            // Инициализация Highload блока
            Loader::includeModule("highloadblock");
            $hlblock = HighloadBlockTable::getList([
                'filter' => ['=NAME' => self::HLB_CODE_BONUS_DEBITING]
            ])->fetch();
            if(!$hlblock){
                throw new \Exception('Нет highload блока для списаний бонусов');
            } 
            
            $entity = HighloadBlockTable::compileEntity($hlblock); 
            $entity_data_class = $entity->getDataClass(); 

            $priceSave = $price; // Сохраняем цену, чтобы потом записать в базу
            $debites = array();
            foreach($arInvoices as $key => $invoice) {
                $xmlId = strval($invoice['ID']).uniqid();
                if($invoice['BONUS_AVAILABLE'] >= $price) {
                    $debite = $price;
                    $price -= $debite;
                    $arInvoices[$key]['BONUS_SPENT'] += $debite;
                    $arInvoices[$key]['BONUS_AVAILABLE'] -= $debite;
                    // Запись накладной
                    $arElementFields = array(
                        'IBLOCK_ID' => $invoice['IBLOCK_ID'],
                        'NAME' => $invoice['NAME'],
                        'ACTIVE' => 'Y',
                        'PROPERTY_VALUES' => array(
                            'USER' => $invoice['USER'],
                            'INVOICE' => $invoice['INVOICE'],
                            'INVOICE_DATE' => $invoice['INVOICE_DATE'],
                            'RETURN_DATE' => $invoice['RETURN_DATE'],
                            'INVOICE_AMOUNT' => $invoice['INVOICE_AMOUNT'],
                            'RETURN_AMOUNT' => $invoice['RETURN_AMOUNT'],
                            'BONUS_PERCENT_ORIGINAL' => $invoice['BONUS_PERCENT_ORIGINAL'],
                            "BONUS_PERCENT" => $invoice['BONUS_PERCENT'],
                            'BONUS_FULL' => $invoice['BONUS_FULL'],
                            'BONUS_SPENT' => $arInvoices[$key]['BONUS_SPENT'],
                            'BONUS_AVAILABLE' => $arInvoices[$key]['BONUS_AVAILABLE'],
                            'CONTRAGENT' => $invoice['CONTRAGENT']
                        )
                    );
                    $result = $elementObject->Update($invoice['ID'], $arElementFields);
                    if(!$result) {
                        throw new \Exception('Ошибка записи накладной');
                    }
                    // Регистрация списания бонусов
                    $data = array(
                        "UF_DOCUMENT" => $invoice['ID'],
                        "UF_AMOUNT"=> $debite,
                        "UF_NAME" => strval($debite),
                        "UF_XML_ID" => $xmlId
                    );
                    $result = $entity_data_class::add($data);
                    if(!$result) {
                        throw new \Exception('Ошибка записи в Highload блок');
                    }                    
                    $debites[] = $xmlId; // XML_ID состоит из ид накладной и суммы
                    if(self::DEBUG) echo 'Списано бонусов '.$debite.' . Списание №'.$result->GetId();
                    break;
                } else {
                    $debite = $invoice['BONUS_AVAILABLE'];
                    $price -= $debite;
                    $arInvoices[$key]['BONUS_SPENT'] += $debite;
                    $arInvoices[$key]['BONUS_AVAILABLE'] -= $debite;
                    // Запись накладной
                    $arElementFields = array(
                        'IBLOCK_ID' => $invoice['IBLOCK_ID'],
                        'NAME' => $invoice['NAME'],
                        'ACTIVE' => 'Y',
                        'PROPERTY_VALUES' => array(
                            'USER' => $invoice['USER'],
                            'INVOICE' => $invoice['INVOICE'],
                            'INVOICE_DATE' => $invoice['INVOICE_DATE'],
                            'RETURN_DATE' => $invoice['RETURN_DATE'],
                            'INVOICE_AMOUNT' => $invoice['INVOICE_AMOUNT'],
                            'RETURN_AMOUNT' => $invoice['RETURN_AMOUNT'],
                            'BONUS_PERCENT_ORIGINAL' => $invoice['BONUS_PERCENT_ORIGINAL'],
                            "BONUS_PERCENT" => $invoice['BONUS_PERCENT'],
                            'BONUS_FULL' => $invoice['BONUS_FULL'],
                            'BONUS_SPENT' => $arInvoices[$key]['BONUS_SPENT'],
                            'BONUS_AVAILABLE' => $arInvoices[$key]['BONUS_AVAILABLE'],
                            'CONTRAGENT' => $invoice['CONTRAGENT']
                        )
                    );
                    $result = $elementObject->Update($invoice['ID'], $arElementFields);
                    if(!$result) {
                        throw new \Exception('Ошибка записи накладной');
                    }
                    // Регистрация списания бонусов
                    $data = array(
                        "UF_DOCUMENT" => $invoice['ID'],
                        "UF_AMOUNT"=> $debite,
                        "UF_NAME" => strval($debite),
                        "UF_XML_ID" => $xmlId
                    );
                    $result = $entity_data_class::add($data);
                    if(!$result) {
                        throw new \Exception('Ошибка записи в Highload блок');
                    }   
                    $debites[] = $xmlId; 
                    if(self::DEBUG) echo 'Списано бонусов '.$debite.' . Списание №'.$result->GetId();            
                } 
            }
            if($price > 0) {
                $DB->Rollback(); 
                \CIBlockElement::SetPropertyValueCode($recordId, "STATUS", "Ошибка: не хватает бонусов");
                if(self::DEBUG) echo 'Ошибка: не хватает бонусов';
                $this->sendMail($recordId);
                return false;
            }

            // Запись списаний в инфоблок "Расходы бонусов"
            \CIBlockElement::SetPropertyValues($recordId, $this->getBonusExpenseIblockId(), $debites, 'DETAIL');
            // Внесение записи в инфоблок "Купленные подарки"
            $iblockId = $this->getGiftCardsBoughtIblockId();
            if(self::DEBUG) echo "Купленные подарки инфоблок $iblockId";
            $arElementFields = array(
                'IBLOCK_ID' => $iblockId,
                'NAME' => $userId."_".$cardName."_".$nominal."_". $curDate->format("d.m.Y"),
                'ACTIVE' => 'Y',
                'PROPERTY_VALUES' => array(
                    'DATE' => $curDate,
                    'PRICE' => $priceSave,
                    'CARD' => $cardId,
                    'CARD_TEXT' => $cardName,
                    'NOMINAL' => $nominal,
                    'USER' => $userId,
                )
            );
            $result = $elementObject->Add($arElementFields);
            if(!$result) {
                throw new \Exception('Ошибка записи в купленные подарки');
            }
            // Конец транзакции     
            $DB->Commit();
        } catch(\Exception $e) {
            $DB->Rollback();
            // Смена статуса операции
            \CIBlockElement::SetPropertyValueCode($recordId, "STATUS", "При списании бонусов возникла ошибка: ".$e->getMessage()); 
            $this->sendMail($recordId);
            if(self::DEBUG) echo $e->getMessage();
            return false;
        }

        // Письмо разработчикам о совершении попытки покупки
        $this->SendAttemptMail($recordId);

        if($resultFlag) {
            if(self::DEBUG) echo "Операция выполнена";
            \CIBlockElement::SetPropertyValueCode($recordId, "STATUS", "Операция выполнена");  

            // Письмо разработчикам и менеджеру об успешной покупке
            $this->SendSuccessMail($recordId);

            return true; // Возвращается успех в случае когда достучались до подарков
        } else {
            \CIBlockElement::SetPropertyValueCode($recordId, "STATUS", $errorMsg); 
            return false; // Возвращается ошибка в случае когда недостучались до подарков
        }
    }

    // Функция в случае ошибки оформления сертификата отправляет менеджеру и админу 
    // почтовое сообщение с информацией об ошибке.ы
    public function sendMail($recordId) {
        $iblock = $this->getBonusExpenseIblockId();
        if(self::DEBUG) echo "Расход бонусов инфоблок ".$iblock;
        $arSelect = Array(
            'ID',
            'NAME',
            'PROPERTY_DATE',
            'PROPERTY_CARD',
            'PROPERTY_NOMINAL',
            'PROPERTY_AMOUNT',
            'PROPERTY_USER_PAY',
            'PROPERTY_TRANSACTION_ID',
            'PROPERTY_USER_ID',
            'PROPERTY_STATUS'
        );
        $arExpenseFields = \CIBlockElement::GetList(array(), array("IBLOCK_ID" => $iblock, "ID" => $recordId), false, false, $arSelect)->Fetch();
        if(!empty($arExpenseFields['PROPERTY_CARD_VALUE'])) {
            $iblock = $this->getGiftCardsIblockId();
            $arSelect = [
                "ID",		
                "NAME",
                "DESCRIPTION", 
                "DETAIL_PICTURE",
                "UF_PRODUCT_ID",
                "UF_URL",
                "UF_EXPIRY_DATE",
                "UF_INSTRUCTION",
            ];
            $arCardFields = \CIBlockSection::GetList(array(), array("IBLOCK_ID" => $iblock, "ID" => $arExpenseFields['PROPERTY_CARD_VALUE']), false, $arSelect)->Fetch();
        } else {
            $arCardFields = Array();
        }
        if(!empty($arExpenseFields['PROPERTY_USER_ID_VALUE']) && $arExpenseFields['PROPERTY_USER_ID_VALUE'] > 0) 
        {
            $arUser = \CUser::GetByID($arExpenseFields['PROPERTY_USER_ID_VALUE'])->Fetch();
        } 
        else 
        {
            $arUser = Array();
        }
        
        $content = "<h1>Текстэль БП: проблема оформления</h1>";
        $content .= "<br>Данные об ошибочной покупке";
        if(!empty($arUser)) {
            $content .= "<br>Покупатель ".$arUser["NAME"]." ".$arUser["LAST_NAME"];
            $content .= " (".$arExpenseFields['PROPERTY_USER_ID_VALUE'].")";
            $content .= "<br>Телефон ".$arUser["PERSONAL_PHONE"];
            $content .= "<br>Адрес электронной почты ".$arUser["EMAIL"];
        } else {
            $content .= "<br>Покупатель не найден (".$arExpenseFields['PROPERTY_USER_ID_VALUE'].")";
        }
        $content .= "<br>Дата покупки ".$arExpenseFields['PROPERTY_DATE_VALUE'];
        if(!empty($arCardFields)) {
            $content .= "<br>Название сертификата \"".$arCardFields["NAME"]."\"";
            $content .= " (".$arCardFields["UF_PRODUCT_ID"].")";
        } else {
            $content .= "<br>Карта не найдена (".$arExpenseFields['PROPERTY_CARD_VALUE'].")";
        }
        $content .= "<br>Номинал ".$arExpenseFields['PROPERTY_NOMINAL_VALUE'];
        $content.= "<br>Причина: <pre>\"".$arExpenseFields['PROPERTY_STATUS_VALUE']."\"</pre>";
        $mailheaders = "Content-type: text/html; charset=utf-8 \r\n";
        $to = self::BP_ERROR_MAIL_TO;
        mail($to, 'Текстэль БП: проблема оформления', $content, $mailheaders);
        if(self::DEBUG) echo " Сообщение отправлено";
    }
    // Вторая вспомогательная функция в случае ошибки оформления сертификата отправляет менеджеру и админу 
    // почтовое сообщение с ТЕКСТОМ ошибки. Вызывается когда еще нет записи в инфоблоке "Расход бонусов"
    public function sendTextMail($message) {
        global $USER;
        $userId = $USER->GetID();
        if(!empty($userId) && $userId > 0) 
        {
            $arUser = \CUser::GetByID($userId)->Fetch();
        } 
        else 
        {
            $arUser = Array();
        }
        $content = "<h1>Текстэль БП: проблема оформления</h1>";
        $content .= "<br>Данные об ошибочной покупке";
        if(!empty($arUser)) {
            $content .= "<br>Покупатель ".$arUser["NAME"]." ".$arUser["LAST_NAME"];
            $content .= " (".$arExpenseFields['PROPERTY_USER_ID_VALUE'].")";
            $content .= "<br>Телефон ".$arUser["PERSONAL_PHONE"];
            $content .= "<br>Адрес электронной почты ".$arUser["EMAIL"];
        } else {
            $content .= "<br>Покупатель не найден (".$arExpenseFields['PROPERTY_USER_ID_VALUE'].")";
        }
        $content .= "<br>Дата покупки ".(new DateTime())->format("d.m.Y h:i:s");
        $content.= "<br>Причина: \"".$message."\"";
        $mailheaders = "Content-type: text/html; charset=utf-8 \r\n";
        $to = self::BP_ERROR_MAIL_TO;
        mail($to, 'Текстэль БП: проблема оформления', $content, $mailheaders);
        if(self::DEBUG) echo " Сообщение отправлено";
    }

    // Отправление письма о попытке покупки
    public function SendAttemptMail($recordId) {
        $iblock = $this->getBonusExpenseIblockId();
        if(self::DEBUG) echo "Расход бонусов инфоблок ".$iblock;
        $arSelect = Array(
            'ID',
            'NAME',
            'PROPERTY_DATE',
            'PROPERTY_CARD',
            'PROPERTY_NOMINAL',
            'PROPERTY_AMOUNT',
            'PROPERTY_USER_PAY',
            'PROPERTY_TRANSACTION_ID',
            'PROPERTY_USER_ID',
            'PROPERTY_STATUS'
        );
        $arExpenseFields = \CIBlockElement::GetList(array(), array("IBLOCK_ID" => $iblock, "ID" => $recordId), false, false, $arSelect)->Fetch();
        if(!empty($arExpenseFields['PROPERTY_CARD_VALUE'])) {
            $iblock = $this->getGiftCardsIblockId();
            $arSelect = [
                "ID",		
                "NAME",
                "DESCRIPTION", 
                "DETAIL_PICTURE",
                "UF_PRODUCT_ID",
                "UF_URL",
                "UF_EXPIRY_DATE",
                "UF_INSTRUCTION",
            ];
            $arCardFields = \CIBlockSection::GetList(array(), array("IBLOCK_ID" => $iblock, "ID" => $arExpenseFields['PROPERTY_CARD_VALUE']), false, $arSelect)->Fetch();
        } else {
            $arCardFields = Array();
        }
        if(!empty($arExpenseFields['PROPERTY_USER_ID_VALUE']) && $arExpenseFields['PROPERTY_USER_ID_VALUE'] > 0) 
        {
            $arUser = \CUser::GetByID($arExpenseFields['PROPERTY_USER_ID_VALUE'])->Fetch();
        } 
        else 
        {
            $arUser = Array();
        }
        
        $content = "<h1>Текстэль БП: совершена попытка покупки сертификата</h1>";
        $content .= "<br>Данные об попытке";
        if(!empty($arUser)) {
            $content .= "<br>Покупатель ".$arUser["NAME"]." ".$arUser["LAST_NAME"];
            $content .= " (".$arExpenseFields['PROPERTY_USER_ID_VALUE'].")";
            $content .= "<br>Телефон ".$arUser["PERSONAL_PHONE"];
            $content .= "<br>Адрес электронной почты ".$arUser["EMAIL"];
        } else {
            $content .= "<br>Покупатель не найден (".$arExpenseFields['PROPERTY_USER_ID_VALUE'].")";
        }
        $content .= "<br>Дата покупки ".$arExpenseFields['PROPERTY_DATE_VALUE'];
        if(!empty($arCardFields)) {
            $content .= "<br>Название сертификата \"".$arCardFields["NAME"]."\"";
            $content .= " (".$arCardFields["UF_PRODUCT_ID"].")";
        } else {
            $content .= "<br>Карта не найдена (".$arExpenseFields['PROPERTY_CARD_VALUE'].")";
        }
        $content .= "<br>Номинал ".$arExpenseFields['PROPERTY_NOMINAL_VALUE'];

        $arFields = array(
            "EMAIL_TO" => self::BP_ERROR_MAIL_TO, // Куда будут посылаться письма-уведомления
            "BODY" => $content
        );
        \CEvent::Send("ATTEMPT_TO_BUY_CARD", array("s1"), $arFields);
        if(self::DEBUG) echo " Сообщение отправлено";
    }

    // Отправление письма о попытке покупки
    public function SendSuccessMail($recordId) {
        $iblock = $this->getBonusExpenseIblockId();
        if(self::DEBUG) echo "Расход бонусов инфоблок ".$iblock;
        $arSelect = Array(
            'ID',
            'NAME',
            'PROPERTY_DATE',
            'PROPERTY_CARD',
            'PROPERTY_NOMINAL',
            'PROPERTY_AMOUNT',
            'PROPERTY_USER_PAY',
            'PROPERTY_TRANSACTION_ID',
            'PROPERTY_USER_ID',
            'PROPERTY_STATUS'
        );
        $arExpenseFields = \CIBlockElement::GetList(array(), array("IBLOCK_ID" => $iblock, "ID" => $recordId), false, false, $arSelect)->Fetch();
        if(!empty($arExpenseFields['PROPERTY_CARD_VALUE'])) {
            $iblock = $this->getGiftCardsIblockId();
            $arSelect = [
                "ID",		
                "NAME",
                "DESCRIPTION", 
                "DETAIL_PICTURE",
                "UF_PRODUCT_ID",
                "UF_URL",
                "UF_EXPIRY_DATE",
                "UF_INSTRUCTION",
            ];
            $arCardFields = \CIBlockSection::GetList(array(), array("IBLOCK_ID" => $iblock, "ID" => $arExpenseFields['PROPERTY_CARD_VALUE']), false, $arSelect)->Fetch();
        } else {
            $arCardFields = Array();
        }
        if(!empty($arExpenseFields['PROPERTY_USER_ID_VALUE']) && $arExpenseFields['PROPERTY_USER_ID_VALUE'] > 0) 
        {
            $arUser = \CUser::GetByID($arExpenseFields['PROPERTY_USER_ID_VALUE'])->Fetch();
        } 
        else 
        {
            $arUser = Array();
        }
        
        $content = "<h1>Текстэль БП: успешная покупка сертификата</h1>";
        $content .= "<br>Данные о покупке";
        if(!empty($arUser)) {
            $content .= "<br>Покупатель ".$arUser["NAME"]." ".$arUser["LAST_NAME"];
            $content .= " (".$arExpenseFields['PROPERTY_USER_ID_VALUE'].")";
            $content .= "<br>Телефон ".$arUser["PERSONAL_PHONE"];
            $content .= "<br>Адрес электронной почты ".$arUser["EMAIL"];
        } else {
            $content .= "<br>Покупатель не найден (".$arExpenseFields['PROPERTY_USER_ID_VALUE'].")";
        }
        $content .= "<br>Дата покупки ".$arExpenseFields['PROPERTY_DATE_VALUE'];
        if(!empty($arCardFields)) {
            $content .= "<br>Название сертификата \"".$arCardFields["NAME"]."\"";
            $content .= " (".$arCardFields["UF_PRODUCT_ID"].")";
        } else {
            $content .= "<br>Карта не найдена (".$arExpenseFields['PROPERTY_CARD_VALUE'].")";
        }
        $content .= "<br>Номинал ".$arExpenseFields['PROPERTY_NOMINAL_VALUE'];
        $content .= "<br>Идентификатор транзакции ".$arExpenseFields['PROPERTY_TRANSACTION_ID'];

        $arFields = array(
            "EMAIL_TO" => self::BP_SUCCESS_MAIL_TO, // Куда будут посылаться письма-уведомления
            "BODY" => $content
        );
        \CEvent::Send("BUY_CARD_SUCCESS", array("s1"), $arFields);
        if(self::DEBUG) echo " Сообщение отправлено";
    }

    public function testBuy() {
        echo 'Запрос к подаркам';
        // Тело запроса
        $str = strval(89049558109);
        $normPhone = '+';
        if(substr($str, 0, 1) == "8") {
            $normPhone .= "7";
        } else {
            $normPhone .= substr($str, 0, 1);
        }
        $normPhone .= '('.substr($str, 1, 3).')'.substr($str, 4, 3).'-'.substr($str, 7, 4);
        debug($normPhone, false, true);
        $postData =[
            "fullname" => 'Сергей',
            "phone" => '0',
            "email" => 's.dmitriev@vmgr.ru',
            "products" => [
                [
                    "product_id" => 18,
                    "nominal" => 100,
                    "quantity" => "1"
                ]
            ]
        ];
        debug(json_encode($postData), false, true);
        // Запрос
        $apiUrl = 'https://karta-podarkov.ru/rest/v1/create?apiKey='; 
        $apiKey = '';
        $apiRequest = $apiUrl . $apiKey;
        $httpClient = new HttpClient();
        $arResult = $httpClient->post($apiRequest, $postData);
        echo "Статус ".$httpClient->getStatus();
        try {
            $arResult = Json::decode($arResult);
            if($arResult["status"] == 'ok') {
                echo "Запрос успешно выполнен";
            } elseif($arResult["status"] == 'error') {
                echo "Ошибка: ".$arResult["message"];

            }
        } catch(\Exception $e) {
            echo "Ошибка";
        }
        debug($arResult, false, true);
        
    }

    // Делает доступными бонусы 
    public function recalculateBonuses() {
        $cancelPeriod = $this->getSettingsArray()["CANCEL_PERIOD"];
        if(self::DEBUG) echo "Период отмены $cancelPeriod ";

        $iblockId = $this->getInvoiceIblockId();
        if(self::DEBUG) echo "Накладные инфоблок $iblockId ";
        $arFilter = ["IBLOCK_ID" => $iblockId, '=PROPERTY_BONUS_SPENT' => 0, '=PROPERTY_BONUS_AVAILABLE' => 0];
		$arSelect = ['ID', 'PROPERTY_BONUS_FULL', 'PROPERTY_RETURN_DATE'];
        $res = \CIBlockElement::GetList(['PROPERTY_RETURN_DATE' => 'ASC'], $arFilter, $arSelect, false);
        $counter = 0;
		while($arFields = $res->Fetch()) {
            $invoiceId = $arFields["ID"];
			$bonusFull = $arFields["PROPERTY_BONUS_FULL_VALUE"];
            $dateReturn = $arFields["PROPERTY_RETURN_DATE_VALUE"];
            // Если дата уже прошла
            if($this->compareDate($dateReturn)) {
                echo "| $bonusFull $dateReturn ";
                \CIBlockElement::SetPropertyValues($invoiceId, $iblockId, $bonusFull, 'BONUS_AVAILABLE');

                $cancelDate = (new \DateTime())->modify("+$cancelPeriod day");
                $cancelDate = DateTime::createFromPhp($cancelDate);
                \CIBlockElement::SetPropertyValues($invoiceId, $iblockId, $cancelDate, 'CANCEL_DATE');
                $counter++;
            }
		}
        if(self::DEBUG) echo " Обновлено $counter накладных";

        // Инициализация Highload блока для списания бонусов
        Loader::includeModule("highloadblock");
        $hlblock = HighloadBlockTable::getList([
            'filter' => ['=NAME' => self::HLB_CODE_BONUS_DEBITING]
        ])->fetch();
        if(!$hlblock){
            throw new \Exception('Нет highload блока для списаний бонусов');
        } 
        $entity = HighloadBlockTable::compileEntity($hlblock); 
        $entity_data_class = $entity->getDataClass(); 

        $iblockId = $this->getInvoiceIblockId();
        if(self::DEBUG) echo "Накладные инфоблок $iblockId ";
        $arFilter = ["IBLOCK_ID" => $iblockId, '!PROPERTY_BONUS_AVAILABLE' => 0];
		$arSelect = ['ID', 'PROPERTY_BONUS_AVAILABLE', 'PROPERTY_CANCEL_DATE'];
        $res = \CIBlockElement::GetList(['PROPERTY_CANCEL_DATE' => 'ASC'], $arFilter, $arSelect, false);
        $counter = 0; // Счетчик аннулированных накладных
        while($arFields = $res->Fetch()) {
            $invoiceId = $arFields["ID"];
            $bonusAvailable = $arFields["PROPERTY_BONUS_AVAILABLE_VALUE"];
            $dateCancel = $arFields["PROPERTY_CANCEL_DATE_VALUE"];
            if(!empty($dateCancel) && $this->compareCancelDate($dateCancel)) {
                echo "| $bonusAvailable ($dateCancel)";
                \CIBlockElement::SetPropertyValues($invoiceId, $iblockId, 0, 'BONUS_AVAILABLE');
                // Регистрация списания бонусов
                $xmlId = strval($invoiceId).uniqid();
                $description = "Аннулирование бонусов. Списано $bonusAvailable баллов с накладной № $invoiceId . Дата списания ".date("d.m.Y");
                $data = array(
                    "UF_DOCUMENT" => $invoiceId,
                    "UF_AMOUNT"=> $bonusAvailable,
                    "UF_NAME" => strval($bonusAvailable),
                    "UF_XML_ID" => $xmlId,
                    "UF_DESCRIPTION" => $description
                );
                $result = $entity_data_class::add($data);
                if(!$result) {
                    throw new \Exception('Ошибка записи в Highload блок');
                }   
                $counter++;
            }    
        }
        if(self::DEBUG) echo " Аннулировано $counter накладных. Скрипт завершил свою работу.";
    }

    // Возвращает true если дата аннулирования баллов наступила или уже прошла (date в формате ДД.ММ.ГГГГ ЧЧ:ММ:СС)
    public function compareCancelDate($date) {
        $cancelDate = new \DateTime($date);
        $todayDate = new \DateTime();
        $returnToday = $todayDate->format("d.m.Y") == $cancelDate->format("d.m.Y");
        $interval = intval(date_diff($todayDate, $cancelDate)->format('%R%a'));
        return ($interval < 0) || $returnToday;
    }

    public function compareWarningDate($date, $period) {
        $cancelDate = new \DateTime($date);
        $todayDate = new \DateTime();
        $interval = intval(date_diff($todayDate, $cancelDate)->format('%R%a'));
        return ($interval >= 0) && ($interval < $period);
    }

    public function getDateDifference($date) {
        $cancelDate = new \DateTime($date);
        $todayDate = new \DateTime();
        $interval = intval(date_diff($todayDate, $cancelDate)->format('%R%a'));
        return $interval;
    }

    public function getSettingsArray() {
        $iblockId = $this->getBonusSettingsIblockId();
        if(self::DEBUG) echo "Настройки инфоблок $iblockId ";
        $arFilter = ["IBLOCK_ID" => $iblockId];
        $arSelect = [
            "PREVIEW_TEXT", 
            "DETAIL_TEXT", 
            "PROPERTY_PRICE", 
            "PROPERTY_CANCEL_PERIOD", 
            "PROPERTY_WARNING_PERIOD",
            "PROPERTY_RULES",
            "PROPERTY_FULL_RULES"
        ];
        $res = \CIBlockElement::GetList([], $arFilter, $arSelect, false)->Fetch();
        $result = [
            "PREVIEW_TEXT" => $res["PREVIEW_TEXT"],
            "DETAIL_TEXT" => $res["DETAIL_TEXT"],
            "PRICE" => intval($res["PROPERTY_PRICE_VALUE"]),
            "CANCEL_PERIOD" => intval($res["PROPERTY_CANCEL_PERIOD_VALUE"]),
            "WARNING_PERIOD" => intval($res["PROPERTY_WARNING_PERIOD_VALUE"]),
            "RULES" => $res["PROPERTY_RULES_VALUE"]["TEXT"],
            "FULL_RULES_PATH" => \CFile::GetPath($res["PROPERTY_FULL_RULES_VALUE"])
        ];
        return $result;
    }

    // Вычисление количество доступных бонусов для текущего юзера
    public function GetAvailableBonusTotal() {
        global $USER;
		// Подготовительные операции
		\CModule::IncludeModule('iblock');
		$userId = $USER->GetID();
		// Вычисление количества доступных бонусов
		$invoiceIblockId = $this->getInvoiceIblockId();
		$arFilter = ["IBLOCK_ID" => $invoiceIblockId, 'PROPERTY_USER' => $userId, '>PROPERTY_BONUS_AVAILABLE' => 0];
		$arSelect = ['PROPERTY_BONUS_AVAILABLE'];
		$res = \CIBlockElement::GetList([], $arFilter, $arSelect, false);
		$totalCredit = 0;
		while($arFields = $res->Fetch()) {
			$totalCredit += $arFields["PROPERTY_BONUS_AVAILABLE_VALUE"];
        }
        return $totalCredit;
    }

    /**
     * Вычисление количество доступных бонусов для юзера с заданным id
     */
    public function getAvailableBonusTotalByUser($userId) {
		// Подготовительные операции
		\CModule::IncludeModule('iblock');
		// Вычисление количества доступных бонусов
		$invoiceIblockId = $this->getInvoiceIblockId();
		$arFilter = ["IBLOCK_ID" => $invoiceIblockId, 'PROPERTY_USER' => $userId, '>PROPERTY_BONUS_AVAILABLE' => 0];
		$arSelect = ['PROPERTY_BONUS_AVAILABLE'];
		$res = \CIBlockElement::GetList([], $arFilter, $arSelect, false);
		$totalCredit = 0;
		while($arFields = $res->Fetch()) {
			$totalCredit += $arFields["PROPERTY_BONUS_AVAILABLE_VALUE"];
        }
        return $totalCredit;
    }

    // Функция возвращает true, если максимум покупок достигнут и нужно отменить действие кнопок купить
    public function isBuyDisabled() {
        global $USER;
        $userId = $USER->GetID();
        // Проверка максимум 1 покупка в день для одного юзера. Такая же проверка в ajax bp.php
        $iblockId = $this->getGiftCardsBoughtIblockId();
        $bought = [];
        $todayBuyCount = 0;
        $arFilter = array(
            'IBLOCK_ID' => $iblockId,
            'PROPERTY_USER' => $userId,
        );
        $res = \CIBlockElement::GetList(array('SORT' => 'ASC'), $arFilter, false, false, ["ID", "NAME", "PROPERTY_DATE"]);
        while($fields = $res->Fetch()) {
            // Собираются все сегодняшние покупки юзера
            $returnDate = new \DateTime($fields["PROPERTY_DATE_VALUE"]);
            $todayDate = new \DateTime();
            if ($todayDate->format("d.m.Y") == $returnDate->format("d.m.Y")) {
                $bought[] = $fields;
                $todayBuyCount++;
            }
        }
        
        if ($todayBuyCount >= self::BP_MAX_BUY) {
            return true;
        } else {
            return false;
        }
    }

    // Функция возвращает ид юзера по ид контрагента, используя библиотеку
    public function getUserByAgent($contragentId) {
        require_once $_SERVER['DOCUMENT_ROOT'] . "/local/lib/vmgr/customers/linkmanager.php";
        $lm = new \Vmgr\Customers\LinkManager();
        $userId = $lm->getUserByAgent($contragentId);
        if(!$userId) {
            $userId = 0;
        }
        return intval($userId);
    }

    /**
     * Возвращает количество доступных + еще не начислесленных бонусов
     * текущего пользователя.
     * Если результат равен нулю, то показывается другой дизайн страницы бонусов
     */
    public function getTotalBonuses()
    {
        // Почти полная копия компонента vmgr:bonus.invoice.list (можно много кода выкинуть)
        global $USER;
        $iblockId = $this->getInvoiceIblockId();
		$userId = $USER->GetID();

		// Блок "Детализация по бонусам"
		$arItems = [];
		$arFilter = ["IBLOCK_ID" => $iblockId, 'PROPERTY_USER' => $userId];
		$arSelect = [  
			'ID', 
			'NAME',
			'PROPERTY_USER',
			'PROPERTY_INVOICE',
			'PROPERTY_INVOICE_DATE',
			'PROPERTY_RETURN_DATE',
			'PROPERTY_CANCEL_DATE',
			'PROPERTY_INVOICE_AMOUNT',
			'PROPERTY_RETURN_AMOUNT',
			'PROPERTY_BONUS_PERCENT_ORIGINAL',
			'PROPERTY_BONUS_PERCENT',
			'PROPERTY_BONUS_FULL',
			'PROPERTY_BONUS_SPENT',
			'PROPERTY_BONUS_AVAILABLE'
		];
		$res = \CIBlockElement::GetList(['PROPERTY_INVOICE_DATE' => 'DESC'], $arFilter, false, ["iNumPage" => 1, "nPageSize" => $pageSize], $arSelect);
		
		while($arFields = $res->Fetch()) {
			$arItems[] = $arFields;
		}
		$this->arResult["ITEMS"] = [];
		$warningPeriod = $this->getSettingsArray()["WARNING_PERIOD"]; // 31 день
		foreach($arItems as $arItem) {
			// Дата отмены если есть
			if(!empty($arItem["PROPERTY_CANCEL_DATE_VALUE"])) {
				$dateCancel = (new \DateTime($arItem["PROPERTY_CANCEL_DATE_VALUE"]))->format("d.m.Y");
			} else {
				$dateCancel = "";
			}
			// Статусы
			if(!$this->compareDate($arItem["PROPERTY_RETURN_DATE_VALUE"])) {
				// Если дата возврата не прошла
				$status = 'not_available';
			} elseif(intval($arItem["PROPERTY_BONUS_AVAILABLE_VALUE"]) <> 0) {

				if(!empty($dateCancel) && $this->compareWarningDate($dateCancel, $warningPeriod)) {
					// Если скоро сгорят баллы предупреждение
					$status = 'warning';
				}else {
					// Если дата возврата прошла и есть неиспользованные бонусы то статус
					$status = 'available';
				}
				
			} else {
				// Если дата возврата прошла и все бонусы потрачены
				$status = 'none';
			}
			$dateAvailable = (new \DateTime($arItem["PROPERTY_RETURN_DATE_VALUE"]))
				->modify('1 day')
				->format("d.m.Y");
			$arResult["ITEMS"][] = [
				"NAME" => $arItem["PROPERTY_INVOICE_VALUE"],
				"DATE" => $arItem["PROPERTY_INVOICE_DATE_VALUE"],
				"SUM" => $arItem["PROPERTY_INVOICE_AMOUNT_VALUE"],
				"SUM_RETURN" => $arItem["PROPERTY_RETURN_AMOUNT_VALUE"],
				"SUM_CREDIT" => $arItem["PROPERTY_INVOICE_AMOUNT_VALUE"] - $arItem["PROPERTY_RETURN_AMOUNT_VALUE"],
				"BONUS_FULL" => $arItem["PROPERTY_BONUS_FULL_VALUE"],
				"BONUS_AVAILABLE" => $arItem["PROPERTY_BONUS_AVAILABLE_VALUE"],
				"BONUS_SPENT" => $arItem["PROPERTY_BONUS_SPENT_VALUE"],
				"DATE_AVAILABLE" => $dateAvailable,
				"DATE_CANCEL" => $dateCancel,
				"STATUS" => $status, // Статус
			];
		}
		$arResult["LAST"] = $res->NavPageNomer == $res->NavPageCount;

		// Сгорание бонусов
		$min = $warningPeriod;
		foreach($arResult["ITEMS"] as $arItem) {
			if($arItem["STATUS"] == 'warning')
			{
				$diff = $this->getDateDifference($arItem["DATE_CANCEL"]);
				if($diff <= $min) {
					$warningDate = $arItem["DATE_CANCEL"]; // Дата ближайшего сгорания
					$min = $diff;
				}
				
			}
		}
		// Ближайшие накладные для аннулирования
		$warningItems = [];
		foreach($arResult["ITEMS"] as $arItem) {
			if($arItem["STATUS"] == 'warning' && $this->getDateDifference($arItem["DATE_CANCEL"]) == $min)
			{
				$warningItems[] = $arItem;
			}
		}
		// Число бонусов для аннулирования
		if(!empty($warningItems)) {
			$warningBonus = 0;
			foreach($warningItems as $arItem) {
				$warningBonus += $arItem["BONUS_AVAILABLE"]; // сколько сгорят бонусов
			}
		} else {
			$warningBonus = 0;
		}
		
		$arResult["WARNING_BONUS"] = $warningBonus; // Ближайшие бонусы, которые сгорят
		$arResult["WARNING_DATE"] = $warningDate; // Дата ближайшего сгорания

		// Суммарные количество недоступных бонусов с датой начисления
		$flag = true;
		$totalNoCredit = 0;
		foreach($arResult["ITEMS"] as $arItem) {
			if($arItem["STATUS"] == 'not_available') {
				$totalNoCredit += $arItem["BONUS_FULL"];
				// Вычисляем самую позднюю дату начисления
				if($flag) {
					$flag = false;
					$dateCredit = $arItem["DATE_AVAILABLE"];
				}
				if(strtotime($arItem["DATE_AVAILABLE"]) > strtotime($dateCredit)) {
					$dateCredit = $arItem["DATE_AVAILABLE"];
				}
					
			}
		}
		// Общее количество доступных бонусов
		$totalCredit = 0;
		foreach($arResult["ITEMS"] as $arItem) {
			if($arItem["BONUS_AVAILABLE"] > 0) {
				$totalCredit += $arItem["BONUS_AVAILABLE"];
			}
		}
		$arResult["TOTAL_CREDIT"] = $totalCredit;
		$arResult["TOTAL_NO_CREDIT"] = $totalNoCredit;
		$arResult["TOTAL"] = $totalNoCredit + $totalCredit;
		$arResult["DATE_CREDIT"] = $dateCredit;
        return $arResult["TOTAL"];
	}

    /**
     * Создание оплаты сертификата
     */
    public function createPayment($userId)
    {
        $iblock = $this->getPaymentIblockId();
        $price = $this->getSettingsArray()["PRICE"];
        $transactionId = "BP-".uniqid(); // Генерация идентификатора оплаты
        $el = new \CIBlockElement;
        $id = $el->Add(array(
            "IBLOCK_ID" => $iblock,
            "NAME" => 'Оплата подарочного сертификата #'.$transactionId.' от ' . date("Y-m-d"),
            "PROPERTY_VALUES" => array(
                'USER_ID' => $userId,
                'TRANSACTION_ID' => $transactionId,
                'DATE' => date('Y-m-d H:i:s'),
                'PRICE' => $price,
                'IS_PAYED' => 0
            )
        ));

        if (!$id) {
            throw new \Exception("Ошибка создания оплаты");
        }

        return $transactionId;
    }

    /**
     * Фиксация оплаты сертификата
     * Возвращает true если оплата найдена и фиксация прошла успешно
     * false если оплата с данным transactionId не найдена
     */
    public function payCard($transactionId, $productId, $nominal)
    {
        $iblock = $this->getPaymentIblockId();
        $arFilter = Array(
            "IBLOCK_ID" => $iblock,
            "PROPERTY_TRANSACTION_ID" => $transactionId
        );
        $arExisting = \CIBlockElement::GetList([], $arFilter, false, false, Array("ID"))->Fetch();
        if ($arExisting["ID"] > 0) {
            $ID = IntVal($arExisting["ID"]);
            \CIBlockElement::SetPropertyValueCode($ID, "PRODUCT_ID", $productId);
            \CIBlockElement::SetPropertyValueCode($ID, "NOMINAL", $nominal);
            \CIBlockElement::SetPropertyValueCode($ID, "IS_PAYED", 1);
            return true;
        } else {
            return false;
        }
    }
}


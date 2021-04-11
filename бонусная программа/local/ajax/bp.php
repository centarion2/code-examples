<?
/** Аякс скрипт для бонусной программы
 * 
 * Используется для подгрузки накладных и подарочных сертификатов
 * Используется для посылки СМС
 * Используется для проверки кода подтверждения
 * Используется для покупки подарочного сертификата
 * Используется для записи лога ошибок оплат и СМС.
 * */
if ($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') exit('Не ajax запрос');
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

// Параметры скрипта
$maxAttempts = 5; // Максимальное число попыток отправить СМС в час
$logPath = '/local/logs/bonuspayments.log'; // Путь к файлу журнала ошибок оплат
$logSms = '/local/logs/sms.log'; // Путь к файлу журнала ошибок СМС

// Отправление SMS
function sendSmsCode ($phoneNumber, $cardName, $nominal, $code)
{
        // Отправка СМС (должен быть настроен соответствующий шаблон СМС)
        $sms = new \Bitrix\Main\Sms\Event(
            'SMS_BYE_GIFT_CONFIRM',
            [
                'USER_PHONE' => $phoneNumber,
                'CODE' => $code,
                'NAME' => $cardName,
                'NOMINAL' => $nominal,
            ]
        );
        $response = $sms->send(true);
    
        // Обработка ошибок
        $errors = $response->getErrorMessages();
        if (empty($errors)) {

            $arResult = ['status' => true, 'message' => 'Code is sent to phone number'];

            
        } else {
            throw new Exception(current($errors));
        }
    return $arResult;
}

// Генерация случайного кода подтверждения
function getSmsMessage()
{
    $smsCode = randString(6, array('0123456789'));

    return array('code' => $smsCode);
}


function checkNumber($phoneNumber)
{
    $phoneNumber = \Bitrix\Main\UserPhoneAuthTable::normalizePhoneNumber($phoneNumber);

    $checkNumber = \Bitrix\Main\UserPhoneAuthTable::validatePhoneNumber($phoneNumber);

    if (is_string($checkNumber) && strlen($checkNumber) > 0)
    {
        $arResult = array('status' => false, 'message' => $checkNumber);
    }
    elseif ($checkNumber)
    {
        $arResult = array('status' => true, 'phoneNumber' => $phoneNumber);
    }
    return $arResult;
}

function getUserById($id)
{
    global $USER;
    /*
    $sortBy = "timestamp_x";
    $sortOrder = "desc";
    $arUser = $USER->GetList(
        $sortBy,
        $sortOrder,
        array('=ID' => $id),
        array('FIELDS' => array('ACTIVE', 'ID', 'PERSONAL_PHONE'), 'SELECT' => array('UF_SMS_CODE', 'UF_SMS_SEND_TIME', 'UF_SMS_SEND_COUNT', 'UF_FIRST_SMS_SEND_TIME'))
    )->Fetch();
    */
    $arUser = \CUser::GetByID($id)->Fetch();

    if (!$arUser)
    {
        $arResult = array('status' => false, 'message' => 'Пользователь не найден');
    }
    else {
        $arUser['PERSONAL_PHONE'] = \Bitrix\Main\UserPhoneAuthTable::normalizePhoneNumber($arUser['PERSONAL_PHONE']);

        $arUser = Array(
            'ACTIVE' => $arUser["ACTIVE"],
            'ID' => $arUser['ID'], 
            'PERSONAL_PHONE' => $arUser['PERSONAL_PHONE'],
            'UF_SMS_CODE' => $arUser['UF_SMS_CODE'], 
            'UF_SMS_SEND_TIME' => $arUser['UF_SMS_SEND_TIME'], 
            'UF_SMS_SEND_COUNT' => $arUser['UF_SMS_SEND_COUNT'], 
            'UF_FIRST_SMS_SEND_TIME' => $arUser['UF_FIRST_SMS_SEND_TIME']
        );
        $arResult = array('status' => true, 'data' => $arUser);
    }
    return $arResult;
}

function getInterval($dateStr)
{
	$now = date("Y-m-d H:i:s");
    return abs(strtotime($now) - strtotime($dateStr));
}

function checkSmsSend($arUser)
{
    global $maxAttempts;
    global $logSms;
    if (IntVal($arUser['UF_SMS_SEND_COUNT']) < $maxAttempts || !$arUser['UF_FIRST_SMS_SEND_TIME'] || (IntVal($arUser['UF_SMS_SEND_COUNT']) >= $maxAttempts && getInterval($arUser['UF_FIRST_SMS_SEND_TIME']) > 3600)) {
        if (!$arUser['UF_SMS_SEND_COUNT'] || $arUser['UF_SMS_SEND_COUNT'] == 1)
        {
            $wait = '2 minutes';
        }
        elseif ($arUser['UF_SMS_SEND_COUNT'] >= 2)
        {
            $wait = '5 minutes';
        }
    
        if ($wait)
        {
            $obLastSendTime = new DateTime($arUser['UF_SMS_SEND_TIME']);
            $obLastSendTime->add($wait);
            $nextSendTime = $obLastSendTime->getTimestamp(); // Время разрешения следующей отправки
    
            $obCurrentTime = new DateTime();
            $currentTime = $obCurrentTime->getTimestamp(); // Текущее время
    
            if ($currentTime < $nextSendTime)
            {
                $waitTime = $nextSendTime - $currentTime;
    
                $arResult = array(
                    'status' => !$arUser['UF_SMS_SEND_TIME'] ? true : false,
                    'message' => 'Получить новый код можно через: ' . $waitTime . ' сек.',
                    'idle_time' => $waitTime
                );
            }
        }
        if (!$arResult)
        {
            $arResult = array('status' => true, 'count' => intval($arUser['UF_SMS_SEND_COUNT']));
        }
    } else {
        //file_put_contents($_SERVER['DOCUMENT_ROOT'].$logSms , "Юзер {$arUser['ID']}. Превышено количество попыток\n", FILE_APPEND);
        $arResult = array('status' => false, 'message' => "Превышено количество попыток");
    }

    

    return $arResult;
}

try {

    // Подгрузка накладных
    if ($_REQUEST['action'] == 'load_orders') { 
        // Параметры

        // Подготовительные операции
        global $USER;
        CModule::IncludeModule('iblock');
        require_once $_SERVER['DOCUMENT_ROOT'] . "/local/lib/vmgr/bonusprogram/bonusprogrammanager.php";
        $bp = Vmgr\BonusProgram\BonusProgramManager::get();
        $pageSize = $bp::BP_LOAD_ORDERS_PAGE_SIZE; /* По сколько элементов загружаем. Должно быть 10
                                                    Должно быть такое же значение переменная pageSize 
                                                    как в компоненте vmgr:bonus.invoices.list
                                                    */
        $iblockId = $bp->getInvoiceIblockId();
        $userId = $USER->GetID();
        //$userId = 1; // для юзер для теста
        // Загрузка данных из БД
        $arItems = [];
        $arFilter = ["IBLOCK_ID" => $iblockId, 'PROPERTY_USER' => $userId];
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
            'PROPERTY_BONUS_FULL',
            'PROPERTY_BONUS_SPENT',
            'PROPERTY_BONUS_AVAILABLE'
        ];
        $res = \CIBlockElement::GetList(['PROPERTY_INVOICE_DATE' => 'DESC'], $arFilter, false, ["iNumPage" => $_REQUEST["page"]+1, "nPageSize" => $pageSize], $arSelect);
        //debug($res, false, true);
        while($arFields = $res->Fetch()) {
            $arItems[] = $arFields;
        }
        $arResult["items"] = [];
        foreach($arItems as $arItem) {
            //debug($arItem, false, true);
            if($bp->compareDate($arItem["PROPERTY_RETURN_DATE_VALUE"])) {
                if($arItem["PROPERTY_BONUS_AVAILABLE_VALUE"] > 0) {
                    // Если дата возврата прошла и есть неиспользованные бонусы то статус
                    $status = 'available';
                } else {
                    // Если дата возврата прошла все бонусы потрачены
                    $status = 'none';
                }
            } else {
                // Если дата возврата не прошла
                $status = 'not_available';
            }
            $arResult["items"][] = [
                "name" => $arItem["PROPERTY_INVOICE_VALUE"],
                "date" => $arItem["PROPERTY_INVOICE_DATE_VALUE"],
                "sum" => CurrencyFormatNumber($arItem["PROPERTY_INVOICE_AMOUNT_VALUE"], 'RUB'),
                "sum_return" => CurrencyFormatNumber($arItem["PROPERTY_RETURN_AMOUNT_VALUE"], 'RUB'),
                "sum_credit" => CurrencyFormatNumber($arItem["PROPERTY_INVOICE_AMOUNT_VALUE"] - $arItem["PROPERTY_RETURN_AMOUNT_VALUE"], 'RUB'),
                "bonus_full" => CurrencyFormatNumber($arItem["PROPERTY_BONUS_FULL_VALUE"], 'RUB'),
                "bonus_available" => CurrencyFormatNumber($arItem["PROPERTY_BONUS_FULL_VALUE"] - $arItem["PROPERTY_BONUS_SPENT_VALUE"], 'RUB'),
                "bonus_spent" => CurrencyFormatNumber($arItem["PROPERTY_BONUS_SPENT_VALUE"], 'RUB'),
                "date_available" => $arItem["PROPERTY_RETURN_DATE_VALUE"],
                "status" => $status, // Статус
            ];
        }
        $arResult["last"] = $res->NavPageNomer == $res->NavPageCount;
    

    // Подгрузка купленных сертификатов
    } elseif($_REQUEST['action'] == 'load_bonus') { 
        // Параметры
        require_once $_SERVER['DOCUMENT_ROOT'] . "/local/lib/vmgr/bonusprogram/bonusprogrammanager.php";
        $bp = Vmgr\BonusProgram\BonusProgramManager::get();
        $pageSize = $bp::BP_LOAD_BONUS_PAGE_SIZE; /* По сколько элементов загружаем. Должно быть 5
                                                    Должно быть такое же значение переменная pageSize 
                                                    как в компоненте vmgr:bonus.giftcards.bought
                                                    */
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
        $res = \CIBlockElement::GetList(array('PROPERTY_DATE' => 'DESC'), $arFilter, false, ["iNumPage" => $_REQUEST["page"]+1, "nPageSize" => $pageSize], $arSelect);
        $arItems = [];
        while($fields = $res->Fetch()) {
            $res2 = \CIBlockSection::GetByID($fields["PROPERTY_CARD_VALUE"]);
            $card = $res2->Fetch();
            $card_picture = CFile::GetPath($card["DETAIL_PICTURE"]);
            $arItems[] = [
                "date" => $fields["PROPERTY_DATE_VALUE"],
                "price" => CurrencyFormatNumber($fields["PROPERTY_PRICE_VALUE"], "RUB"),
                "picture" => $card_picture,
                "card_text" => $fields["PROPERTY_CARD_TEXT_VALUE"],
                "nominal" => CurrencyFormatNumber($fields["PROPERTY_NOMINAL_VALUE"], "RUB")
            ];
        }
        //debug( $userId, false, true);
        $arResult["items"] = $arItems;
        $arResult["last"] = $res->NavPageNomer == $res->NavPageCount;

    
    // Отправление смс с кодом подтверждения
    } elseif($_REQUEST['action'] == 'send_sms') { 
        
        $productId = intval($_REQUEST["product_id"]); // Номер сертификата число
        $nominal = intval($_REQUEST["nominal"]); // Номинал сертификата число

        if(!$productId || !$nominal) {
            throw new Exception("Не выбран сертификат");
        }
        // Определяем название сертификата
        require_once $_SERVER['DOCUMENT_ROOT'] . "/local/lib/vmgr/bonusprogram/bonusprogrammanager.php";
        $bp = Vmgr\BonusProgram\BonusProgramManager::get();
        $iblockId = $bp->getGiftCardsIblockId();
        $arFilter = array(
            'IBLOCK_ID' => $iblockId,
            "=UF_PRODUCT_ID" => $productId,
            );
		$res = \CIBlockSection::GetList(array('SORT' => 'ASC'), $arFilter, false, ["NAME"]);
		if($fields = $res->Fetch()) {
			$cardName = $fields["NAME"];
		} else {
            $cardName = "";
        }
    
        // Получение и нормализация номера телефона
        if (!$USER->IsAuthorized()) {
            throw new Exception("Вы не авторизованы");
        }
        $userId = $USER->GetID();
        if(!$userId) {
            throw new Exception("Нет такого пользователя");
        }
        $arUser = \CUser::GetByID($userId)->Fetch();
        $userPhone = \Bitrix\Main\UserPhoneAuthTable::normalizePhoneNumber($arUser['PERSONAL_PHONE']);
        if (!$userPhone) {
            throw new Exception('Для пользователя не задан номер телефона');
        }

        $checkNumber = checkNumber($userPhone);

        if ($checkNumber['status'])
        {
            $phoneNumber = $checkNumber['phoneNumber'];

            $arFoundUser = getUserById($userId);


            if ($arFoundUser['status'])
            {
                $arUser = $arFoundUser['data'];

                $checkSmsSend = checkSmsSend($arUser);
                
                if ($checkSmsSend['status'])
                {
                    $arSms = getSmsMessage();

                    $obCurrentTime = new DateTime();

                    if (!$arUser['UF_FIRST_SMS_SEND_TIME'] || (IntVal($arUser['UF_SMS_SEND_COUNT']) >= $maxAttempts && getInterval($arUser['UF_FIRST_SMS_SEND_TIME']) > 3600)) {
                        // Обнуление попыток
                        //file_put_contents($_SERVER['DOCUMENT_ROOT'].$logSms , "Юзер {$arUser['ID']}. Обнуление попыток".PHP_EOL, FILE_APPEND);
                        $arUser['UF_FIRST_SMS_SEND_TIME'] = $obCurrentTime->format('d.m.Y H:i:s');
                        $arUser['UF_SMS_SEND_COUNT'] = 0;
                    }

                    $arUser['UF_SMS_CODE'] = $arSms['code'];
                    $arUser['UF_SMS_SEND_TIME'] = $obCurrentTime->format('d.m.Y H:i:s');
                    $arUser['UF_SMS_SEND_COUNT']++;
                    
                    if (!$USER->Update($arUser['ID'], $arUser))
                    {
                        $arResult = array('status' => false, 'message' => $USER->LAST_ERROR, 'idle_time' => 0);
                    }
                    else {
                        //file_put_contents($_SERVER['DOCUMENT_ROOT'].$logSms , "Юзер {$arUser['ID']}. Отправлено СМС № {$arUser['UF_SMS_SEND_COUNT']} с кодом {$arUser['UF_SMS_CODE']}".PHP_EOL, FILE_APPEND);
                        $arResult = sendSmsCode($phoneNumber, $cardName, $nominal, $arSms['code']);
                        $arResult['idle_time'] = $checkSmsSend['idle_time'];
                    }
                }
                else $arResult = $checkSmsSend;
            }
            else $arResult = $arFoundUser;
        }
        else $arResult = $checkNumber;


    // Проверка кода подтверждения  
    } elseif($_REQUEST['action'] == 'buy_card') {
        
        if (!$USER->IsAuthorized())
        {
            throw new Exception('Вы не авторизованы');
        }
        $userId = $USER->GetID();
        if(!$userId) {
            throw new Exception("Нет такого пользователя");
        }
        $arUser = \CUser::GetByID($userId)->Fetch();
        $userPhone = \Bitrix\Main\UserPhoneAuthTable::normalizePhoneNumber($arUser['PERSONAL_PHONE']);
        if (!$userPhone) {
            throw new Exception('Для пользователя не задан номер телефона');
        }

        if (strlen($_REQUEST['code']) <= 0)
        {
            throw new Exception('Введите код из SMS');
        }
        else
        {
            $code = trim($_REQUEST['code']);
        }

        $arFoundUser = getUserById($userId);

        if ($arFoundUser['status'])
        {
            $arUser = $arFoundUser['data'];

            if ($code == $arUser['UF_SMS_CODE'])
            {
                // Создание оплаты
                require_once $_SERVER['DOCUMENT_ROOT'] . "/local/lib/vmgr/bonusprogram/bonusprogrammanager.php";
                $bp = Vmgr\BonusProgram\BonusProgramManager::get();
                $transactionId = $bp->createPayment($userId);
                $arResult = ['status' => true, 'message' => 'Код корректен', 'idle_time' => 0, 'transaction_id' => $transactionId];
            }
            else {
                $arResult = array('status' => false, 'message' => 'Код из SMS указан неверно');
            }
            $arResult['idle_time'] = checkSmsSend($arUser)['idle_time'];
        }
        else $arResult = $arFoundUser;      
       

    // Оплачено, совершается покупка
    }elseif($_REQUEST['action'] == 'success_pay') {
        $productId = intval($_REQUEST["product_id"]);
        $nominal = intval($_REQUEST["nominal"]);
        $transactionId = $_REQUEST["transaction_id"];

        require_once $_SERVER['DOCUMENT_ROOT'] . "/local/lib/vmgr/bonusprogram/bonusprogrammanager.php";
        $bp = Vmgr\BonusProgram\BonusProgramManager::get();
        
        // Фиксация оплаты сертификата
        $bp->payCard($transactionId, $productId, $nominal);

        // Проверка максимум 1 покупка в день для одного юзера. Такая же проверка в функции buyCard
        $userId = $USER->GetID();
        $iblockId = $bp->getGiftCardsBoughtIblockId();
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
        if ($todayBuyCount >= Vmgr\BonusProgram\BonusProgramManager::BP_MAX_BUY) {
            // Больше одной покупки в день, выдается ошибка, покупка не совершается
            throw new Exception('Больше одной покупки в день');
        }

        if($bp->buyCard($transactionId, $productId, $nominal)) {
            $arResult = ['status' => true, 'message' => 'Покупка совершена.', 'idle_time' => $idleTime];
        } else {
            $arResult = ['status' => false, 'message' => 'Ошибка при покупке сертификата.', 'idle_time' => $idleTime];
        }
        



    // Неоплачено, запись в лог
    }elseif($_REQUEST['action'] == 'error_pay') {
        $record = PHP_EOL."Оплата не прошла.";
        $time = (new \DateTime("now"))->format('d.m.Y H:i:s');
        $record .= " Время оплаты ".$time.".";
        $email = \Bitrix\Main\Web\Json::decode($_REQUEST["options"])["data"]['cloudPayments']['customerReceipt']['Email'];
        $record .= " Email пользователя: \"".$email."\".";
        $reasonMessage = \Bitrix\Main\Web\Json::decode($_REQUEST["reason"]);
        if(!empty($reasonMessage)) {
            $record .= " Причина: ".$reasonMessage;
        }
        $description = \Bitrix\Main\Web\Json::decode($_REQUEST["options"])["description"];
        $record .= " Описание: $description.";
        file_put_contents($_SERVER['DOCUMENT_ROOT'].$logPath , $record, FILE_APPEND);
        $arResult = ['status' => true, 'message' => 'Запись в лог совершен успешно.', 'idle_time' => $idleTime];
    } else {
    
    }
}
catch (Exception $e) {
    $arResult = ['status' => false, 'message' => $e->getMessage(), 'idle_time' => $idleTime];
} 
finally {
    echo \Bitrix\Main\Web\Json::encode($arResult);
}




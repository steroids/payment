<?php

namespace steroids\payment\providers;

use steroids\core\structure\RequestInfo;
use steroids\payment\enums\PaymentStatus;
use steroids\payment\exceptions\PaymentProcessException;
use steroids\payment\exceptions\SignatureMismatchRequestException;
use steroids\payment\models\PaymentOrderInterface;
use steroids\payment\structure\PaymentProcess;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

/**
 * Class RobokassaProvider
 * See API Examples: https://docs.robokassa.ru/#2400
 * @package steroids\payment\providers
 */
class RobokassaProvider extends BaseProvider
{

    /**
     * @var string
     */
    public $login;

    /**
     * @var string
     */
    public $password1;

    /**
     * @var string
     */
    public $password2;

    /**
     * @var string
     */
    public $url = 'https://auth.robokassa.ru/Merchant/Index.aspx';

    /**
     * @param int|float $amount
     * @return float
     */
    protected static function normalizeAmount($amount)
    {
        $amount = (string)$amount;
        return substr($amount, 0, -2) . '.' . substr($amount, -2) . '0000';
    }

    /**
     * @param string $description
     * @return string
     */
    protected static function normalizeDescription($description, $length = 100)
    {
        $description = preg_replace('/[^0-9a-zа-я,.-:?! ]+/iu', '', $description);
        $description = mb_substr($description, 0, $length);
        return $description;
    }

    /**
     * @inheritDoc
     */
    public function start(PaymentOrderInterface $order, RequestInfo $request)
    {
        $params = []; // TODO

        // Format additional params for Robokassa
        $shpParams = [];
        foreach ($params as $key => $value) {
            $shpParams['Shp_' . $key] = $value;
        }

        // Normalize amount
        $amount = static::normalizeAmount($order->getOutAmount());

        // Generate receipt
        $receipt = Json::encode([
            // Система налогообложения.
            // Необязательное поле, если у организации имеется только один тип налогообложения.
            'sno' => '',
            'items' => [
                [
                    // Обязательное поле. Наименование товара. Строка, максимальная длина 64 символа.
                    // Если в наименовании товара Вы используете специальные символы, например кавычки,
                    // то их обязательно необходимо экранировать.
                    'name' => static::normalizeDescription($order->getDescription(), 64),

                    // Обязательное поле. Полная сумма в рублях за все количество данного товара с
                    // учетом всех возможных скидок, бонусов и специальных цен.
                    'sum' => static::normalizeAmount($order->getOutAmount()),

                    // Обязательное поле. Количество/вес конкретной товарной позиции.
                    // Десятичное число: целая часть не более 5 знаков, дробная часть не более 3 знаков.
                    'quantity' => 1.0,

                    // Признак способа расчёта.
                    // Этот параметр необязательный. Если этот параметр не передан клиентом, то в чеке
                    // будет указано значение параметра по умолчанию из Личного кабинета, если же параметр
                    // передан клиентом, то именно эти значения параметра будут переданы в АТОЛ.
                    'payment_method' => null,

                    // Признак способа расчёта.
                    // Этот параметр необязательный. Если этот параметр не передан клиентом, то в чеке
                    // будет указано значение параметра из Личного кабинета, если же параметр передан
                    // клиентом, то именно это значение параметра будут переданы в АТОЛ.
                    'payment_object' => null,

                    // Это поле устанавливает налоговую ставку в ККТ. Определяется для каждого вида
                    // товара по отдельности, но за все единицы конкретного товара вместе.
                    //   «none» – без НДС;
                    //   «vat0» – НДС по ставке 0%;
                    //   «vat10» – НДС чека по ставке 10%;
                    //   «vat110» – НДС чека по расчетной ставке 10/110;
                    //   «vat20» – НДС чека по ставке 20%;
                    //   «vat120» – НДС чека по расчетной ставке 20/120.
                    'tax' => 'none',

                    // Маркировка товара, передаётся в виде кода товара. Максимальная длина – 32 байта
                    // (32 символа). Параметр является обязательным только для тех магазинов, которые
                    // продают товары подлежащие обязательной маркировке. В соответствии с распоряжением
                    // правительства РФ №792-р.
                    'nomenclature_code' => null,
                ],
            ]
        ]);

        return new PaymentProcess([
            'request' => new RequestInfo([
                'url' => $this->url,
                'params' => array_merge($shpParams, [
                    // Идентификатор магазина – обозначение магазина ТОЛЬКО для использования интерфейсом инициализации
                    // оплаты, то есть для понимания системой ROBOKASSA в адрес какого магазина будет проводиться
                    // платеж. Идентификатор может содержать латинские буквы, цифры и символы: . - _.
                    // https://docs.robokassa.ru/#2392
                    'MerchantLogin' => $this->login,

                    // Сумма, которую хочет получить магазин. Исходя из этой суммы и текущих курсов валют для каждой
                    // валюты/варианта оплаты в списке будет рассчитана сумма, которую должен будет заплатить клиент.
                    'OutSum' => static::normalizeAmount($order->getOutAmount()),

                    // Номер счета в магазине
                    'InvId' => $order->getId(),

                    // Описание покупки, можно использовать только символы английского или русского алфавита,
                    // цифры и знаки препинания. Максимальная длина — 100 символов. Эта информация отображается в
                    // интерфейсе ROBOKASSA и в Электронной квитанции, которую мы выдаём клиенту после успешного
                    // платежа. Корректность отображения зависит от необязательного параметра Encoding
                    'Description' => static::normalizeDescription($order->getDescription()),

                    // From demo: $crc = md5("$mrh_login:$out_summ:$inv_id:$mrh_pass1:Shp_item=$shp_item");
                    'SignatureValue' => $this->generateSignature(
                        [
                            $this->login,
                            $amount,
                            $order->getId(),
                            $receipt,
                            $this->password1
                        ],
                        $shpParams
                    ),

                    // Код валюты, для которой нужно произвести расчет суммы к оплате. Если оставить этот
                    // параметр пустым, расчет будет произведен для всех доступных валют.
                    'IncCurrLabel' => '',

                    // Язык, использовавшийся при совершении оплаты. В соответствии с ISO 3166-1.
                    'Culture' => 'ru',

                    // Кодировка, в которой отображается страница ROBOKASSA. По умолчанию: Windows-1251.
                    // Этот же параметр влияет на корректность отображения описания покупки (Description) в
                    // интерфейсе ROBOKASSA, и на правильность передачи Дополнительных пользовательских параметров,
                    // если в их значениях присутствует язык отличный от английского.
                    'Encoding' => 'utf-8',

                    // Для работы в тестовом режиме  обязателен параметр  IsTest
                    // Внимание! Для работы в тестовом режиме используется специальный тестовый набор паролей,
                    // не совпадающих с основными рабочими паролями Вашего магазина. Они прописываются в специальном
                    // блоке в Технических настройках Вашего магазина. Это делается для обеспечения безопасности
                    // Вашего интернет-магазина, чтобы злоумышленник не имел возможности «обмануть» Ваш интернет-магазин
                    'IsTest' => $this->testMode,

                    'Receipt' => $receipt,
                ]),
            ]),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function callback(PaymentOrderInterface $order, RequestInfo $request)
    {
        $this->validateSignature($order, $request->params);

        return new PaymentProcess([
            'newStatus' => PaymentStatus::SUCCESS,
            'responseText' => 'OK' . $order->getId(),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function resolveOrderId(RequestInfo $request)
    {
        return ArrayHelper::getValue($request->params, 'InvId');
    }

    /**
     * @inheritDoc
     */
    public function resolveErrorMessage(RequestInfo $request)
    {
        return null;
    }

    protected function validateSignature(PaymentOrderInterface $order, array $params)
    {
        $remoteSignature = ArrayHelper::getValue($params, 'SignatureValue');
        if (!$remoteSignature) {
            throw new PaymentProcessException('Not found params SignatureValue');
        }

        // Get shp params signature
        $shpParams = array_filter($params, fn($key) => strpos($key, 'Shp_') === 0, ARRAY_FILTER_USE_KEY);

        // Generate signature
        // From demo: $my_crc = strtoupper(md5("$out_summ:$inv_id:$mrh_pass2:Shp_item=$shp_item"));
        $signature = $this->generateSignature(
            [
                static::normalizeAmount($order->getOutAmount()),
                $order->getId(),
                $this->password2
            ],
            $shpParams
        );

        // Check md5 hash
        if (strcmp(strtoupper($remoteSignature), strtoupper($signature)) !== 0) {
            throw new SignatureMismatchRequestException($params);
        }
    }

    /**
     * @param array $baseParams
     * @param array $shpParams
     * @return string
     */
    protected function generateSignature(array $baseParams, array $shpParams)
    {
        // Params should be ordered by alphabet in MD5 signature
        ksort($shpParams);

        // Calculates a string which should be added to signature base if a request to Robakassa was made with params.
        // See https://docs.robokassa.ru/#1250
        $shpSignatureItems = [];
        foreach ($shpParams as $key => $value) {
            $shpSignatureItems[] = $key . '=' . $value;
        }

        return strtoupper(md5(implode(':', array_merge($baseParams, $shpSignatureItems))));
    }
}

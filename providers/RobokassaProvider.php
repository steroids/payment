<?php

namespace steroids\payment\providers;

use steroids\core\structure\RequestInfo;
use steroids\payment\enums\PaymentStatus;
use steroids\payment\exceptions\PaymentProcessException;
use steroids\payment\exceptions\SignatureMismatchRequestException;
use steroids\payment\models\PaymentOrderInterface;
use steroids\payment\structure\PaymentProcess;
use yii\helpers\ArrayHelper;

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
    public $url;

    /**
     * @param int|float $amount
     * @return float
     */
    protected static function normalizeAmount($amount)
    {
        return round($amount, 2);
    }

    /**
     * @param string $description
     * @return string
     */
    protected static function normalizeDescription($description)
    {
        $description = preg_replace('/[^0-9a-zа-я,.-:?!]+/', '', $description);
        $description = mb_substr($description, 0, 100);
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

        // Remote url
        $url = $this->url ?: ($this->testMode ? 'https://test.robokassa.ru/Index.aspx' : 'https://auth.robokassa.ru/Merchant/Index.aspx');

        // Normalize amount
        $amount = static::normalizeAmount($order->getOutAmount());

        return new PaymentProcess([
            'request' => new RequestInfo([
                'url' => $url,
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
                ]),
            ])
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

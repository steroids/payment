<?php

namespace steroids\payment\providers;

use steroids\core\structure\RequestInfo;
use steroids\payment\enums\PaymentStatus;
use steroids\payment\exceptions\PaymentProcessException;
use steroids\payment\exceptions\SignatureMismatchRequestException;
use steroids\payment\models\PaymentOrderInterface;
use steroids\payment\structure\PaymentProcess;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

/**
 * Class PayeerProvider
 * Docs: https://www.payeer.com/upload/pdf/PayeerMerchantru.pdf
 * @package steroids\payment\providers
 */
class PayeerProvider extends BaseProvider
{
    /**
     * Идентификатор мерчанта зарегистрированного в системе Payeer на который будет совершен платеж
     * @var string|null
     */
    public ?string $shopId = null;

    /**
     * Секретный ключ
     * @var string|null
     */
    public ?string $secretKey = null;

    /**
     * Валюта платежа
     * Возможные валюты: USD, RUB, EUR, BTC, ETH, BCH, LTC, DASH, USDT, XRP
     * @var string|null
     */
    public ?string $currency = 'USD';

    /**
     * IP адреса Payeer, с которым возможны callback запросы
     * @var array|string[]
     */
    public array $ips = [
        '185.71.65.92',
        '185.71.65.189',
        '149.202.17.210'
    ];

    /**
     * @param int|float $amount
     * @return float
     */
    protected static function normalizeAmount($amount)
    {
        $amount = str_pad((string)$amount, 3, '0', STR_PAD_LEFT);
        return substr($amount, 0, -2) . '.' . substr($amount, -2);
    }

    /**
     * @param string $description
     * @return string|null
     */
    protected static function normalizeDescription($description)
    {
        return $description ? base64_encode($description) : '';
    }

    /**
     * @inheritDoc
     */
    public function start(PaymentOrderInterface $order, RequestInfo $request)
    {
        return new PaymentProcess([
            'request' => new RequestInfo([
                'url' => Url::to(['/payment/payment/proxy-post'], true),
                'params' => [
                    '_url' => 'https://payeer.com/merchant/',

                    // Идентификатор мерчанта
                    'm_shop' => $this->shopId,

                    // В этом поле продавец задает идентификатор покупки в соответствии со своей системой учета.
                    // Желательно использовать уникальный номер для каждого платежа.
                    // Идентификатор должен представлять собой любую строку длиной не больше 32 символов из символов: "A-z", "_", "0-9".
                    'm_orderid' => $order->getId(),

                    // Сумма платежа, которую продавец желает получить от покупателя. Сумма должна быть больше
                    // нуля, дробная часть отделяется точкой, количество знаков после точки - два знака.
                    // Пример: 1.00
                    'm_amount' => static::normalizeAmount($order->getOutAmount()),

                    // Валюта платежа
                    // Возможные валюты: USD, RUB, EUR, BTC, ETH, BCH, LTC, DASH, USDT, XRP
                    'm_curr' => $this->currency,

                    // Описание товара или услуги. Формируется продавцом. Строка добавляется в назначение платежа.
                    // Кодируется алгоритмом base64.
                    'm_desc' => self::normalizeDescription($order->getDescription()),

                    // Контрольная подпись, которая используется для проверки целостности полученной
                    // информации и однозначной идентификации отправителя
                    'm_sign' => $this->generateToken($order),

                    'm_process' => 'send',
                ]
            ]),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function callback(PaymentOrderInterface $order, RequestInfo $request)
    {
        // Validate ip
        if (!in_array(\Yii::$app->request->userIP, $this->ips)) {
            throw new PaymentProcessException('Wrong ip address: ' . \Yii::$app->request->userIP);
        }

        // Validate token
        $order->setExternalId($request->getParam('m_operation_id'));
        $this->validateToken($request->params);

        return new PaymentProcess([
            'newStatus' => $request->getParam('m_status') === 'success' ? PaymentStatus::SUCCESS : null,
            'responseText' => $order->getId() . '|' . $request->getParam('m_status'),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function resolveOrderId(RequestInfo $request)
    {
        return ArrayHelper::getValue($request->params, 'm_orderid');
    }

    /**
     * @inheritDoc
     */
    public function resolveErrorMessage(RequestInfo $request)
    {
        return null;
    }

    /**
     * @param array $params
     * @throws PaymentProcessException
     * @throws SignatureMismatchRequestException
     */
    protected function validateToken(array $params)
    {
        $remoteToken = ArrayHelper::getValue($params, 'm_sign');
        if (!$remoteToken) {
            throw new PaymentProcessException('Not found param m_sign');
        }


        $values = [
            $_POST['m_operation_id'],
            $_POST['m_operation_ps'],
            $_POST['m_operation_date'],
            $_POST['m_operation_pay_date'],
            $_POST['m_shop'],
            $_POST['m_orderid'],
            $_POST['m_amount'],
            $_POST['m_curr'],
            $_POST['m_desc'],
            $_POST['m_status']
        ];
        if (isset($_POST['m_params'])) {
            $values[] = $_POST['m_params'];
        }
        $values[] = $this->secretKey;

        $token = strtoupper(hash('sha256', implode(':', $values)));
        if (strcmp(strtoupper($remoteToken), strtoupper($token)) !== 0) {
            throw new SignatureMismatchRequestException($params);
        }
    }

    protected function generateToken(PaymentOrderInterface $order)
    {
        $values = [
            $this->shopId,
            $order->getId(),
            static::normalizeAmount($order->getOutAmount()),
            $this->currency,
            self::normalizeDescription($order->getDescription()),
            $this->secretKey
        ];

        // TODO Custom params support

        return strtoupper(hash('sha256', implode(':', $values)));
    }
}

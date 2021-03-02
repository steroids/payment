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
 * Class QiwiProvider
 * Test cards (максимум 10 рублей!, https://developer.qiwi.com/ru/payments/#test_data):
 *  - Успешно: 4716282676664270 01/22 111
 *  - Не успешно: 4716282676664270 02/22 111
 *  - Успешно с задержкой в 3 секунды: 4716282676664270 03/22 111
 *  - Не успешно с задержкой в 3 секунды: 4716282676664270 04/22 111
 * @package steroids\payment\providers
 */
class QiwiProvider extends BaseProvider
{
    /**
     * @var string|null
     */
    public ?string $siteId;

    /**
     * @var string|null
     */
    public ?string $secretKey;

    /**
     * Валюта в буквенном формате согласно ISO 4217.
     * @var string
     */
    public string $currency = 'RUB';

    /**
     * @var array|string[]
     */
    public array $ips = [
        '79.142.16.0/20',
        '195.189.100.0/22',
        '91.232.230.0/23',
        '91.213.51.0/24',
    ];

    /**
     * @see https://developer.qiwi.com/ru/payments/#invoice_put
     * @inheritDoc
     */
    public function start(PaymentOrderInterface $order, RequestInfo $request)
    {
        $params = [
            'amount' => [
                // Сумма операции (округленная до двух десятичных знаков в меньшую сторону).
                'value' => round($order->getOutAmount() / 100, 2),

                // Валюта в буквенном формате согласно ISO 4217.
                'currency' => $this->currency,
            ],

            // Дата, до которой счет будет доступен для оплаты. Если счет не будет оплачен до этой даты,
            // ему присваивается финальный статус EXPIRED и последующая оплата станет невозможна.
            'expirationDateTime' => date('c', strtotime('+1 days')),

            // Описание услуги, которую получает Плательщик.
            'comment' => mb_substr($order->getDescription(), 1000),

            'customer' => [
                // Уникальный идентификатор Покупателя в системе ТСП.
                'account' => $order->getPayerUserId(),

                // E-mail Покупателя.
                // TODO 'email' => '',

                // Контактный телефон Покупателя.
                // TODO 'phone' => '',
            ],

            // URL отправки callback.
            //'callbackUrl' => $this->module->getCallbackUrl($order->getMethodName()),
        ];

        $response = $this->httpSend(
            'https://api.qiwi.com/partner/payin/v1/sites/' . $this->siteId . '/bills/' . $order->getId(),
            $params
        );

        if (!ArrayHelper::getValue($response, 'payUrl')) {
            throw new PaymentProcessException('Not found payment url. Wrong response: ' . print_r($response, true));
        }

        return new PaymentProcess([
            'request' => RequestInfo::createFromUrl(ArrayHelper::getValue($response, 'payUrl')),
        ]);
    }

    /**
     * @see https://developer.qiwi.com/ru/payments/#callback
     * @inheritDoc
     */
    public function callback(PaymentOrderInterface $order, RequestInfo $request)
    {
        // TODO Check ip masks in callback

        if ($request->getParam('type') !== 'PAYMENT') {
            return new PaymentProcess([
                'responseText' => '',
            ]);
        }

        $order->setExternalId($request->getParam('payment.paymentId'));
        $this->validateToken($request);

        return new PaymentProcess([
            'newStatus' => $request->getParam('payment.status.value') === 'SUCCESS' ? PaymentStatus::SUCCESS : null,
            'responseText' => 'OK',
        ]);
    }

    /**
     * @inheritDoc
     */
    public function resolveOrderId(RequestInfo $request)
    {
        return ArrayHelper::getValue($request->params, 'payment.billId');
    }

    /**
     * @inheritDoc
     */
    public function resolveErrorMessage(RequestInfo $request)
    {
        return ArrayHelper::getValue($request->params, 'status.reasonMessage')
            ?: ArrayHelper::getValue($request->params, 'status.errorCode');
    }

    /**
     * @see https://developer.qiwi.com/ru/payments/#notifications_auth
     * @param RequestInfo $request
     * @throws PaymentProcessException
     * @throws SignatureMismatchRequestException
     */
    protected function validateToken(RequestInfo $request)
    {
        $remoteToken = $request->getHeader('Signature');
        if (!$remoteToken) {
            throw new PaymentProcessException('Not found params Token');
        }

        $values = implode('|', [
            $request->getParam('payment.paymentId'),
            $request->getParam('payment.createdDateTime'),
            $request->getParam('payment.amount.value'),
        ]);
        $token = hash_hmac('sha256', $values, $this->secretKey);
        if (strcmp(strtolower($remoteToken), strtolower($token)) !== 0) {
            throw new SignatureMismatchRequestException(array_merge(
                $request->params,
                ['Signature' => $remoteToken]
            ));
        }
    }

    /**
     * @param string $url
     * @param array $params
     * @return array
     */
    protected function httpSend(string $url, array $params = [])
    {
        $data = file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'PUT',
                'header' => implode("\n", [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->secretKey,
                ]),
                'content' => Json::encode($params),
                'ignore_errors' => true,
            ],
        ]));

        return $data ? Json::decode($data) : null;
    }
}

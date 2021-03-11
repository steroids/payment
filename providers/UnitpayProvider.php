<?php


namespace steroids\payment\providers;


use app\billing\enums\CurrencyEnum;
use steroids\billing\models\BillingCurrency;
use steroids\core\structure\RequestInfo;
use steroids\payment\enums\PaymentStatus;
use steroids\payment\exceptions\PaymentProcessException;
use steroids\payment\models\PaymentOrderInterface;
use steroids\payment\structure\PaymentProcess;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

class UnitpayProvider extends BaseProvider
{

    public string $secretKey;

    public string $publicKey;

    /**
     * @see https://help.unitpay.ru/book-of-reference/payment-system-codes
     * @var string
     */
    public string $paymentType;

    public string $projectId;

    public string $currency = 'usd';

    /**
     * @inheritDoc
     */
    public function start(PaymentOrderInterface $order, RequestInfo $request)
    {
        $params = [
            'paymentType' => $this->paymentType,
            'projectId' => $this->projectId,
            'resultUrl' => $this->module->getSuccessUrl($order->getMethodName()),
            'desc' => $order->description,
            'account' => $order->payerUser->email,
            'signature' => $this->getFormSignature($order),
            'sum' => round($order->getOutAmount() / 100, 2),
            'currency' => $this->currency,
        ];

        $response = $this->httpSend('https://unitpay.ru/api', array_merge(
            ['method' => 'initPayment'],
            ['params' => $params]
        ));

        if (!isset($response['result']['redirectUrl'])) {
            throw new PaymentProcessException('Not found payment url. Wrong response: ' . print_r($response, true));
        }

        return new PaymentProcess([
            'request' => new RequestInfo([
                'url' => $response['result']['redirectUrl'],
                'method' => RequestInfo::METHOD_GET,
            ]),
        ]);
    }

    /**
     * @param PaymentOrderInterface $order
     * @return string
     */
    private function getFormSignature($order)
    {
        $currency = BillingCurrency::getByCode(CurrencyEnum::USD);

        $hashStr = $order->payerUser->email . '{up}' . $this->currency . '{up}' . $order->description . '{up}' . $currency->amountToFloat($order->inAmount) . '{up}' . $this->secretKey;

        return hash('sha256', $hashStr);
    }

    /**
     * @inheritDoc
     */
    public function callback(PaymentOrderInterface $order, RequestInfo $request)
    {
        $order->setExternalId($request->getParam('paymentId'));

        $response = $this->httpSend('https://unitpay.ru/api', array_merge(
            ['method' => 'getPayment'],
            ['params' => [
                'paymentId' => $request->getParam('paymentId'),
                'secretKey' => $this->secretKey
            ]]
        ));

        if (!isset($response['result']['status'])) {
            throw new PaymentProcessException('Not found payment status. Wrong response: ' . print_r($response, true));
        }

        $statusMap = [
            'success' => Yii::t('steroids', 'Успешный платеж'),
            'wait' => Yii::t('steroids', 'Ожидание оплаты'),
            'error' => Yii::t('steroids', 'Ошибка платежа'),
            'error_pay' => Yii::t('steroids', 'Ошибка/отказ магазина на стадии PAY, в статистике как "незавершен"'),
            'error_check' => Yii::t('steroids', 'Ошибка/отказ магазина на стадии CHECK, в статистике как "отклонен"'),
            'refund' => Yii::t('steroids', 'Возврат средств покупателю'),
            'secure' => Yii::t('steroids', 'На проверке у службы безопасности банка'),
        ];

        return new PaymentProcess([
            'newStatus' => ArrayHelper::getValue($statusMap, $response['result']['status']),
            'responseText' => 'ok',
        ]);
    }

    /**
     * @inheritDoc
     */
    public function resolveOrderId(RequestInfo $request)
    {
        return ArrayHelper::getValue($request->params, 'paymentId');
    }

    /**
     * @inheritDoc
     */
    public function resolveErrorMessage(RequestInfo $request)
    {
        return null;
    }

    /**
     * @param string $url
     * @param array $params
     * @return array
     */
    protected function httpSend(string $url, array $params = [])
    {
        $data = http_build_query($params);
        $getUrl = $url . "?" . $data;

        $curlSession = curl_init();
        curl_setopt($curlSession, CURLOPT_URL, $getUrl);
        curl_setopt($curlSession, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);

        $response = json_decode(curl_exec($curlSession), true);
        curl_close($curlSession);

        return $response;
    }
}
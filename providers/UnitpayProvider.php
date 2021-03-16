<?php


namespace steroids\payment\providers;


use steroids\core\structure\RequestInfo;
use steroids\payment\enums\PaymentStatus;
use steroids\payment\exceptions\PaymentProcessException;
use steroids\payment\interfaces\ProviderWithdrawInterface;
use steroids\payment\models\PaymentOrderInterface;
use steroids\payment\structure\PaymentProcess;
use Yii;
use yii\helpers\ArrayHelper;

class UnitpayProvider extends BaseProvider implements ProviderWithdrawInterface
{

    public string $secretKey;

    /**
     * @see https://help.unitpay.ru/book-of-reference/payment-system-codes
     * @var string
     */
    public string $paymentType = 'card';

    public string $projectId;

    public string $currency = 'usd';

    //email в Unitpay
    public string $login;

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
            'sum' => round($order->getOutAmount() / 100, 2),
            'currency' => $this->currency,
        ];

        $params['signature'] = $this->getSignature($params);

        $response = $this->httpSend('https://unitpay.ru/api', array_merge(
            ['method' => 'initPayment'],
            ['params' => $params]
        ));

        if (!isset($response['result']['redirectUrl'])) {
            throw new PaymentProcessException('Not found payment url. Wrong response: ' . print_r($response, true));
        }

        return new PaymentProcess([
            'request' => RequestInfo::createFromUrl($redirectUrl),
        ]);
    }

    private function getSignature(array $params, $method = null)
    {
        $params = $this->filterSignatureParameters($params);

        ksort($params);
        $params[] = $this->secretKey;

        if ($method) {
            array_unshift($params, $method);
        }

        return hash('sha256', implode('{up}', $params));
    }

    private function filterSignatureParameters(array $params)
    {
        $allowedKeys = array('account', 'desc', 'sum', 'currency');

        return array_intersect_key($params, array_flip($allowedKeys));
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

        switch ($response['result']['status']) {
            case ('success'):
                $newStatus = PaymentStatus::SUCCESS;
                break;
            case ('wait'):
                $newStatus = PaymentStatus::PROCESS;
                break;
            default:
                $newStatus = PaymentStatus::FAILURE;
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

        if ($newStatus === PaymentStatus::FAILURE) {
            $order->setErrorMessage(
                ArrayHelper::getValue($statusMap, $response['result']['status'])
            );
        }

        return new PaymentProcess([
            'newStatus' => $newStatus,
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
     * @see https://tele-port.github.io/#transfer-card
     * @param PaymentOrderInterface $order
     * @return PaymentProcess
     */
    public function withdraw(PaymentOrderInterface $order): PaymentProcess
    {
        $params = [
            'login' => $this->login,
            'sum' => round($order->getOutAmount() / 100, 2),
            'transactionId' => '', //??
            'purse' => $order->methodParams['cardNumber'],
            'paymentType' => 'card',
            'secretKey' => $this->secretKey,
        ];

        $response = $this->httpSend('https://unitpay.ru/api', array_merge(
            ['method' => 'massPayment'],
            ['params' => $params]
        ));

        return new PaymentProcess([
            'newStatus' => (bool)ArrayHelper::getValue($response, 'result.status')
                ? PaymentStatus::SUCCESS
                : PaymentStatus::PROCESS,
            'responseText' => 'ok',
        ]);
    }

    /**
     * @param string $url
     * @param array $params
     * @return array
     */
    protected function httpSend(string $url, array $params = [])
    {
        $requestUrl = $url . '?' . http_build_query($params, null, '&', PHP_QUERY_RFC3986);

        return json_decode(file_get_contents($requestUrl), true);
    }
}
<?php


namespace steroids\payment\providers;


use app\billing\enums\CurrencyEnum;
use steroids\billing\models\BillingCurrency;
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

    public string $publicKey;

    /**
     * @see https://help.unitpay.ru/book-of-reference/payment-system-codes
     * @var string
     */
    public string $paymentType;

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

        switch ($response['result']['status']){
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

        if($newStatus === PaymentStatus::FAILURE){
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
            'newStatus' => (bool)ArrayHelper::getValue($response, 'result.status') ? PaymentStatus::SUCCESS : PaymentStatus::PROCESS,
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
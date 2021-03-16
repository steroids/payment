<?php


namespace steroids\payment\providers;


use steroids\core\structure\RequestInfo;
use steroids\payment\enums\PaymentStatus;
use steroids\payment\exceptions\PaymentProcessException;
use steroids\payment\models\PaymentOrderInterface;
use steroids\payment\structure\PaymentProcess;
use yii\helpers\ArrayHelper;

class ImpayProdiver extends BaseProvider
{

    public string $merchantKey;

    public int $login;

    public int $paymentTimeout = 10;

    /**
     * @inheritDoc
     */
    public function start(PaymentOrderInterface $order, RequestInfo $request)
    {
        $params = [
            'amount' => round($order->getOutAmount() / 100, 2),
            'document_id' => $order->id,
            'fullname' => $order->payerUser->firstName,
            'extid' => $order->id,
            'timeout' => $this->paymentTimeout,
            'successurl' => $this->module->getSuccessUrl($order->getMethodName()),
            'failurl' => $this->module->getFailureUrl($order->getMethodName()),
            'cancelurl' => $this->module->getFailureUrl($order->getMethodName()),
        ];

        $response = $this->httpSend('https://test.impay.ru:806/v1/pay/lk', $params);

        if (!isset($response['url'])) {
            throw new PaymentProcessException('Not found payment url. Wrong response: ' . print_r($response, true));
        }

        return new PaymentProcess([
            'request' => RequestInfo::createFromUrl($response['url']),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function callback(PaymentOrderInterface $order, RequestInfo $request)
    {

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
            'card' => 'сard',
            'cardnum' => $order->methodParams['cardNumber'],
            'amount' => round($order->getOutAmount() / 100, 2),
            'extid' => $order->id,
            'document_id' => $order->id,
            'fullname' => $order->payerUser->firstName,
        ];

        $response = $this->httpSend('https://test.impay.ru:806/v1/out/paycard', $params);

        if ($response['status'] === 0) {
            throw new PaymentProcessException('Wrong response: ' . print_r($response, true));
        }

        return new PaymentProcess([
            'newStatus' => $response['status'] === 1
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
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => array(
                'Content-Type:application/json',
                'X-Login:'.$this->login,
                'X-Token:'.$this->generateToken($params),
            ),
        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
    }

    private function generateToken($params)
    {
        return sha1(sha1($this->merchantKey) . sha1(json_encode($params)));
    }
}
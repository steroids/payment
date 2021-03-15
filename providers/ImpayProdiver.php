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

    public string $merchantKey = 'fN&Z7a94G1K#3QTx5U67K48rXb9!19AXO0542DA';

    public int $paymentTimeout = 20;

    /**
     * @inheritDoc
     */
    public function start(PaymentOrderInterface $order, RequestInfo $request)
    {
        $params = [
            'amount' => round($order->getOutAmount() / 100, 2),
//            'document_id' ???
            'fullname' => $order->payerUser->firstName,
            'extid' => $order->id,
            'timeout' => $this->paymentTimeout,
            'successurl' => $this->module->getSuccessUrl($order->getMethodName()),
            'failurl' => $this->module->getFailureUrl($order->getMethodName()),
        ];

        $response = $this->httpSend('https://unitpay.ru/api', $params);

        if (!isset($response['url'])) {
            throw new PaymentProcessException('Not found payment url. Wrong response: ' . print_r($response, true));
        }

        return new PaymentProcess([
            'request' => new RequestInfo([
                'url' => $response['url'],
                'method' => RequestInfo::METHOD_GET,
            ]),
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
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => [
                'X-Token' => $this->generateToken($params)
            ]
        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
    }

    private function generateToken($params)
    {
        return sha1(sha1($this->merchantKey) + sha1(json_encode($params)));
    }
}
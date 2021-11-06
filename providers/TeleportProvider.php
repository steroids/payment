<?php

namespace steroids\payment\providers;

use steroids\billing\exceptions\BillingException;
use steroids\billing\models\BillingCurrency;
use steroids\core\structure\RequestInfo;
use steroids\payment\enums\PaymentStatus;
use steroids\payment\exceptions\PaymentProcessException;
use steroids\payment\exceptions\SignatureMismatchRequestException;
use steroids\payment\interfaces\ProviderWithdrawInterface;
use steroids\payment\models\PaymentOrderInterface;
use steroids\payment\structure\PaymentProcess;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\Url;

/**
 * Class TeleportProvider
 * Docs: https://tport.nl/Teleport-SCI.pdf
 * @package steroids\payment\providers
 */
class TeleportProvider extends BaseProvider implements ProviderWithdrawInterface
{
    const PAYMENT_SYSTEM_CARD_RU = 'card ru';
    const PAYMENT_SYSTEM_CARD_KZ = 'card kz';
    const PAYMENT_SYSTEM_CARD_UA = 'card ua';
    const PAYMENT_SYSTEM_P2P_CARD = 'P2pCard';

    const METHOD_WITHDRAWAL = 'withdrawal';
    const METHOD_TRANSFER_CARD = 'transfer-card';

    /**
     * Ваш e-mail, для которого создан данный SCI
     * @var string|null
     */
    public ?string $accountEmail = null;

    /**
     * Ваш уникальный идентификатор ("Название sci")
     * @var string|null
     */
    public ?string $sciName = null;

    /**
     * Пароль вашего SCI (был указан при его создании)
     * @var string|null
     */
    public ?string $sciPassport = null;

    /**
     * @var string
     */
    public string $currency = 'USD';

    /**
     * @var string
     */
    public string $withdrawApiBaseUrl = 'https://api.tport.nl/rest/';

    /**
     * Счет в определенной валюте в системе телепорта. С этого счета делается вывод
     * Пример USDT-12345AB
     * @var string
     */
    public string $withdrawWallet;

    /**
     * @var string
     */
    public string $withdrawSystemName = 'card ru';

    /**
     * @var string
     */
    public string $withdrawApiKey;

    /**
     * @var string
     */
    public string $withdrawSecretKey;

    /**
     * @var integer
     */
    public int $withdrawApiVersion = 1;

    /**
     * @var integer
     */
    public int $withdrawApiTimeout = 20;

    public $currencyCodeMap = [
        'usd' => 'USD',
        'usdt' => 'USDT',
    ];

    /**
     * @param PaymentOrderInterface $order
     * @param RequestInfo $request
     * @return PaymentProcess
     * @throws BillingException
     * @throws PaymentProcessException
     */
    public function start(PaymentOrderInterface $order, RequestInfo $request)
    {
        $outCurrency = BillingCurrency::getByCode($order->getOutCurrencyCode());
        $amount = $outCurrency->amountToFloat($order->getOutAmount());

        return new PaymentProcess([
            'request' => new RequestInfo([
                'url' => Url::to(['/payment/payment/proxy-post'], true),
                'params' => [
                    '_url' => 'https://pay.tport.nl/ru',
                    't_account_email' => $this->accountEmail,
                    't_sci_name' => $this->sciName,
                    't_amount' => $amount,
                    't_currency' => $this->getInternalCurrency($order->getOutCurrencyCode()),
                    't_order_id' => $order->getId(),
                ]
            ]),
        ]);
    }

    /**
     * @see https://tele-port.github.io/#transfer-card
     * @param PaymentOrderInterface $order
     * @return PaymentProcess
     * @throws PaymentProcessException
     * @throws BillingException
     */
    public function withdraw(PaymentOrderInterface $order): PaymentProcess
    {
        $this->withdrawSystemName = ArrayHelper::getValue($order->methodParams, 'withdrawSystemName', $this->withdrawSystemName);

        // Uncomment for use cache:
        $paymentSystems = json_decode('{"success":1,"data":[{"id":"1","name":"Bitcoin"},{"id":"2","name":"PerfectMoney"},{"id":"3","name":"Dash"},{"id":"4","name":"Advcash"},{"id":"5","name":"Ethereum"},{"id":"6","name":"TetherUsd"},{"id":"7","name":"Litecoin"},{"id":"10","name":"BitcoinCash"},{"id":"11","name":"Payeer"},{"id":"12","name":"Teleport"},{"id":"13","name":"CardRu"},{"id":"14","name":"CardKz"},{"id":"15","name":"CardUa"},{"id":"16","name":"AlfaP2p"},{"id":"17","name":"P2p"},{"id":"18","name":"TetherTrc20"}]}', true);
        //$paymentSystems = $this->tportQuery('payment-systems', null, 'GET', true);

        $paymentSystemsMap = ArrayHelper::map($paymentSystems['data'], 'name', 'id');
        $systemId = ArrayHelper::getValue($paymentSystemsMap, $this->withdrawSystemName);
        if (!$systemId) {
            $order->log('Not found system id for name "' . $this->withdrawSystemName . '". Available: ' . Json::encode($paymentSystems));
            return new PaymentProcess();
        }

        $outCurrency = BillingCurrency::getByCode($order->getOutCurrencyCode());

        /**
         * В случае метода withdrawal amount = это сумма, которая будет списана со счета wallet,
         * преобразована в to_currency и отправлена на address.
         * Если выводим 100 с рублевого счета в usdt, то, отправится ~1.3usdt
         */
        $data = [
            'wallet' => $this->withdrawWallet,
            'amount' => $outCurrency->amountToFloat($order->getOutAmount()),
            'system' => $systemId,
        ];

        $method = $this->isTransferCard() ? static::METHOD_TRANSFER_CARD : static::METHOD_WITHDRAWAL;
        $data = array_merge($data,
            $method === static::METHOD_TRANSFER_CARD
                ? $this->getTransferCardData($order, $systemId)
                : $this->getWithdrawalData($order, $systemId)
        );


        $order->log("POST {$method} " . Json::encode($data));
        $result = $this->tportQuery($method, $data, 'POST', true);
        $order->log('Response: ' . Json::encode($result));

        if (isset($result['error']['text'])) {
            $order->setErrorMessage($result['error']['text']);
        }

        return new PaymentProcess([
            'newStatus' => ArrayHelper::getValue($result, 'success') ? PaymentStatus::SUCCESS : PaymentStatus::PROCESS,
            'responseText' => 'ok',
        ]);
    }

    /**
     * @inheritDoc
     */
    public function callback(PaymentOrderInterface $order, RequestInfo $request)
    {
        $order->setExternalId($request->getParam('t_id'));
        if ($request->getParam('t_currency') === $this->currency) {
            $order->setExternalAmount(((int)$request->getParam('t_amount')) * 100);
        }

        $this->validateToken($request->params);

        return new PaymentProcess([
            'newStatus' => PaymentStatus::SUCCESS,
            'responseText' => 'ok',
        ]);
    }

    /**
     * @inheritDoc
     */
    public function resolveOrderId(RequestInfo $request)
    {
        return ArrayHelper::getValue($request->params, 't_order_id');
    }

    /**
     * @inheritDoc
     */
    public function resolveErrorMessage(RequestInfo $request)
    {
        return null;
    }

    /**
     * @param PaymentOrderInterface $order
     * @return array
     */
    protected function getTransferCardData(PaymentOrderInterface $order)
    {
        return [
            'card' => $order->methodParams['cardNumber'],
        ];
    }

    /**
     * @param PaymentOrderInterface $order
     * @return array
     * @throws PaymentProcessException
     */
    protected function getWithdrawalData(PaymentOrderInterface $order)
    {
        return [
            'address' => $order->methodParams['address'],
            'to_currency' => $this->getInternalCurrency($order->getOutCurrencyCode()),
        ];
    }

    /**
     * @param array $params
     * @throws PaymentProcessException
     * @throws SignatureMismatchRequestException
     */
    protected function validateToken(array $params)
    {
        $remoteToken = ArrayHelper::getValue($params, 'hash');
        if (!$remoteToken) {
            throw new PaymentProcessException('Not found param hash');
        }

        $token = hash('sha256', implode('', [
            $params['t_account_email'],
            $params['t_sci_name'],
            $params['t_amount'],
            $params['t_currency'],
            $params['t_order_id'],
            $params['t_id'],
            $this->sciPassport
        ]));
        if (strcmp(strtoupper($remoteToken), strtoupper($token)) !== 0) {
            throw new SignatureMismatchRequestException($params);
        }
    }

    /**
     * @see https://github.com/tele-port/tele-port.github.io/blob/main/TportApi.php
     * @param $method
     * @param null $data
     * @param string $httpType
     * @param false $signed
     * @return mixed
     * @throws \Exception
     */
    protected function tportQuery($method, $data = null, $httpType = 'GET', $signed = false)
    {
        $url = rtrim($this->withdrawApiBaseUrl, '/') . '/v' . $this->withdrawApiVersion . '/' . trim($method, '/');
        $headers = ['Content-Type' => 'application/json'];

        if ($signed) {
            $timestamp = time() * 1000;
            $data['timestamp'] = $timestamp;
        }
        $query = is_array($data) ? json_encode($data) : $data;

        if ($signed) {
            $headers['X-TPORT-APIKEY'] = $this->withdrawApiKey;
            $headers['Signature'] = $this->tportCreateSignature($query);
        }

        $opt = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36',
            CURLOPT_TIMEOUT => $this->withdrawApiTimeout,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_FILETIME => true,
            CURLOPT_CUSTOMREQUEST => $httpType,
        ];
        if ($query) {
            $opt += [
                CURLOPT_POSTFIELDS => $query
            ];
        }

        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = $name . ': ' . $value;
        }
        $opt += [
            CURLOPT_HTTPHEADER => $curlHeaders,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $opt);
        $r = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        $ret = json_decode($r, true);

        if ($ret === false || $info['http_code'] != 200) {
            throw new \Exception(__METHOD__ . ':' . $url . ' ' . $query . PHP_EOL . $r);
        }

        return $ret;
    }

    /**
     * @see https://github.com/tele-port/tele-port.github.io/blob/main/TportApi.php
     * @param $query
     * @return string
     */
    protected function tportCreateSignature($query)
    {
        return hash_hmac('sha256', $query, $this->withdrawSecretKey, false);
    }

    protected function getInternalCurrency($currency)
    {
        if (!array_key_exists($currency, $this->currencyCodeMap)) {
            throw new PaymentProcessException("Internal Teleport currency not found for: {$currency}");
        }

        return $this->currencyCodeMap[$currency];
    }

    private function isTransferCard()
    {
        $transferCardPaymentMap = [
            static::PAYMENT_SYSTEM_CARD_RU,
            static::PAYMENT_SYSTEM_CARD_KZ,
            static::PAYMENT_SYSTEM_CARD_UA,
            static::PAYMENT_SYSTEM_P2P_CARD,
        ];

        return in_array($this->withdrawSystemName, $transferCardPaymentMap);
    }
}

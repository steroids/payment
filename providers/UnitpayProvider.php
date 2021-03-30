<?php


namespace steroids\payment\providers;


use app\billing\enums\CurrencyEnum;
use steroids\billing\models\BillingCurrency;
use steroids\core\structure\RequestInfo;
use steroids\core\structure\UrlInfo;
use steroids\payment\enums\PaymentStatus;
use steroids\payment\exceptions\PaymentProcessException;
use steroids\payment\interfaces\ProviderWithdrawInterface;
use steroids\payment\models\PaymentOrderInterface;
use steroids\payment\structure\PaymentProcess;
use yii\base\NotSupportedException;
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

    private const METHOD_TYPE_CHECK = 'check';
    private const METHOD_TYPE_PAY = 'pay';
    private const METHOD_TYPE_PREAUTH = 'preauth';

    private const SECCESS_STATUS = 'success';
    private const PROCESS_STATUS = 'not_completed';
    private const ERROR_STATUS = 'error';

    protected const UNITPAY_CURRENCY_RUB = 'rub';
    protected const UNITPAY_CURRENCY_USD = 'usd';

    protected static function getUnitpayCurrencyToInternalCurrency()
    {
        return [
            static::UNITPAY_CURRENCY_RUB => CurrencyEnum::RUB,
            static::UNITPAY_CURRENCY_USD => CurrencyEnum::USD,
        ];
    }

    /**
     * @inheritDoc
     */
    public function start(PaymentOrderInterface $order, RequestInfo $request)
    {
        $unitpayCurrencyCode = array_search($order->outCurrencyCode, static::getUnitpayCurrencyToInternalCurrency());

        if ($unitpayCurrencyCode === false) {
            throw new PaymentProcessException("Payment order's currency doesn't have a map in Unitpay currencies");
        }

        $params = [
            'paymentType' => $this->paymentType,
            'account' => $order->payerUser->email,
            'sum' => round($order->getOutAmount() / 100, 2),
            'projectId' => $this->projectId,
            'resultUrl' => $this->module->getSuccessUrl($order->getMethodName()),
            'desc' => $order->description,
            'currency' => $unitpayCurrencyCode,
        ];

        if ($this->testMode) {
            $params = array_merge($params, [
                'ip' => '127.0.0.1',
                'test' => 1,
                'currency' => 'rub',
                'secretKey' => $this->secretKey,
            ]);
        }

        $params['signature'] = $this->getSignature($params);

        $response = $this->httpSend('https://unitpay.money/api', array_merge(
            ['method' => 'initPayment'],
            ['params' => $params]
        ));

        if (!isset($response['result']['redirectUrl']) || !isset($response['result']['paymentId'])) {
            throw new PaymentProcessException('Not found payment url. Wrong response: ' . print_r($response, true));
        }

        $order->setExternalId($response['result']['paymentId']);

        $info = new UrlInfo($response['result']['redirectUrl']);

        return new PaymentProcess([
            'request' => new RequestInfo([
                'url' => $info->protocol . '://' . $info->host . $info->path,
                'params' => array_map(function ($param) {
                    return urldecode($param);
                }, $info->params),
            ]),
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

    protected static function validateResponseSignature(RequestInfo $request, string $secretKey): bool
    {
        $params = $request->getParam('params');
        unset($params['signature']);
        ksort($params);
        $stringToHash = $request->getParam('method') . '{up}' . implode('{up}', $params) . '{up}' . $secretKey;

        return $request->getParam('params.signature') === hash('sha256', $stringToHash);
    }

    /**
     * @inheritDoc
     */
    public function callback(PaymentOrderInterface $order, RequestInfo $request)
    {
        $status = strtolower($request->getParam('method'));

        if (!static::validateResponseSignature($request, $this->secretKey) || !static::validatePayment($order, $request)) {
            throw new PaymentProcessException(\Yii::t('steroids', 'Incorrect signature or params data'));
        }

        switch ($status) {
            case self::METHOD_TYPE_PAY:
                $newStatus = PaymentStatus::SUCCESS;
                break;
            case self::METHOD_TYPE_CHECK:
            case self::METHOD_TYPE_PREAUTH:
                $newStatus = PaymentStatus::PROCESS;
                break;
            default:
                $newStatus = PaymentStatus::FAILURE;
        }

        return new PaymentProcess([
            'newStatus' => $newStatus,
            'responseText' => $newStatus === PaymentStatus::FAILURE
                ? self::buildErrorResponse($request->getParam('params.errorMessage'))
                : self::buildSuccessResponse(),
        ]);
    }

    private static function validatePayment(PaymentOrderInterface $order, RequestInfo $request): bool
    {
        $unitpayCurrencyCode = mb_strtolower($request->getParam('params.payerCurrency'));
        if (!$unitpayCurrencyCode) {
            return false;
        }

        $internalCurrencyCode = static::getUnitpayCurrencyToInternalCurrency()[$unitpayCurrencyCode] ?? null;
        if (!$internalCurrencyCode) {
            return false;
        }

        $currency = BillingCurrency::getByCode($internalCurrencyCode);

        return $internalCurrencyCode === $order->outCurrencyCode &&
            (int)$currency->amountToInt($request->getParam('params.orderSum')) === $order->getOutAmount();
    }

    /**
     * @inheritDoc
     */
    public function resolveOrderId(RequestInfo $request)
    {
        throw new NotSupportedException("Unitpay response doesn't contain PaymentOrder ID");
    }

    /**
     * @inheritDoc
     */
    public function resolveErrorMessage(RequestInfo $request)
    {
        return $request->getParam('params.errorMessage');
    }

    /**
     * @see https://help.unitpay.money/payouts/create_payout
     * @param PaymentOrderInterface $order
     * @return PaymentProcess
     */
    public function withdraw(PaymentOrderInterface $order): PaymentProcess
    {
        $params = [
            'login' => $this->login,
            'sum' => round($order->getOutAmount() / 100, 2),
            'transactionId' => $order->id,
            'purse' => $order->methodParams['cardNumber'],
            'paymentType' => 'card',
            'secretKey' => $this->secretKey,
        ];

        $response = $this->httpSend('https://unitpay.money/api', array_merge(
            ['method' => 'massPayment'],
            ['params' => $params]
        ));

        switch (ArrayHelper::getValue($response, 'result.status')) {
            case self::SECCESS_STATUS:
                $newStatus = PaymentStatus::SUCCESS;
                break;
            case self::PROCESS_STATUS:
                $newStatus = PaymentStatus::PROCESS;
                break;
            default:
                $newStatus = PaymentStatus::FAILURE;
        }

        return new PaymentProcess([
            'newStatus' => $newStatus,
            'responseText' => $newStatus === PaymentStatus::FAILURE
                ? self::buildErrorResponse($response['error']['message'] ?? 'withdraw error')
                : self::buildSuccessResponse(),
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

        $response = json_decode(file_get_contents($requestUrl), true);

        return $response;
    }

    private static function buildSuccessResponse(): string
    {
        return json_encode(['result' => ['message' => 'OK']]);
    }

    private static function buildErrorResponse(string $errorText): string
    {
        return json_encode(['error' => ['message' => $errorText]]);
    }

    public static function isCheckRequest(RequestInfo $request): bool
    {
        return strtolower($request->getParam('method')) === self::METHOD_TYPE_CHECK;
    }

    /**
     * Special handle for the 'check' requests
     * @see https://help.unitpay.ru/payments/payment-handler
     *
     * @todo we should check if this payment request should be processed
     *
     * @return string
     */
    public static function getResponseForCheckRequest(): string
    {
        return self::buildSuccessResponse();
    }
}
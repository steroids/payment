<?php

namespace steroids\payment\providers;

use app\billing\enums\CurrencyEnum;
use steroids\billing\models\BillingCurrency;
use steroids\core\structure\RequestInfo;
use steroids\payment\enums\PaymentStatus;
use steroids\payment\exceptions\PaymentProcessException;
use steroids\payment\exceptions\SignatureMismatchRequestException;
use steroids\payment\interfaces\ProviderWithdrawInterface;
use steroids\payment\models\PaymentOrderInterface;
use steroids\payment\structure\PaymentProcess;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

/**
 * Class TeleportProvider
 * Docs: https://tport.nl/Teleport-SCI.pdf
 * @package steroids\payment\providers
 */
class TeleportProvider extends BaseProvider implements ProviderWithdrawInterface
{
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
    public string $withdrawWallet;

    /**
     * @var string
     */
    public string $withdrawApiKey;

    /**
     * @var string
     */
    public string $withdrawSecretKey;

    /**
     * @inheritDoc
     */
    public function start(PaymentOrderInterface $order, RequestInfo $request)
    {
        return new PaymentProcess([
            'request' => new RequestInfo([
                'url' => Url::to(['/payment/payment/proxy-post'], true),
                'params' => [
                    '_url' => 'https://pay.tport.nl/ru',
                    't_account_email' => $this->accountEmail,
                    't_sci_name' => $this->sciName,
                    't_amount' => round($order->getOutAmount() / 100, 2),
                    't_currency' => $this->currency,
                    't_order_id' => $order->getId(),
                ]
            ]),
        ]);
    }

    /**
     * @see https://tele-port.github.io/#transfer-card
     * @param PaymentOrderInterface $order
     * @return PaymentProcess
     */
    public function withdraw(PaymentOrderInterface $order): PaymentProcess
    {
        $currency = BillingCurrency::getByCode(CurrencyEnum::USD);

        $jsonWithdrawData = json_encode([
            'wallet' => $this->withdrawWallet,
            'card' => $order->methodParams['cardNumber'],
            'amount' => $currency->amountToFloat($order->inAmount),
            'timestamp' => time() * 1000,
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.tport.nl/rest/v1/transfer-card');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonWithdrawData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'X-TPORT-APIKEY: ' . $this->withdrawApiKey,
            'Signature: ' . hash_hmac('sha256', $jsonWithdrawData, $this->withdrawSecretKey),
            'Content-Type: application/json',
        ));

        $result = curl_exec($ch);
        $order->log($result);
        curl_close($ch);

        $resultData = json_decode($result);

        return new PaymentProcess([
            'newStatus' => (bool)ArrayHelper::getValue($resultData, 'success') ? PaymentStatus::SUCCESS : null,
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
}

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
 * Class TeleportProvider
 * Docs: https://tport.nl/Teleport-SCI.pdf
 * @package steroids\payment\providers
 */
class TeleportProvider extends BaseProvider
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
                    't_currency' => 'USD',
                    't_order_id' => $order->getId(),
                ]
            ]),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function callback(PaymentOrderInterface $order, RequestInfo $request)
    {
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
            $this->sciPassport
        ]));
        if (strcmp(strtoupper($remoteToken), strtoupper($token)) !== 0) {
            throw new SignatureMismatchRequestException($params);
        }
    }
}

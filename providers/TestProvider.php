<?php

namespace steroids\payment\providers;

use steroids\payment\models\PaymentOrderInterface;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;
use steroids\core\structure\RequestInfo;
use steroids\payment\enums\PaymentStatus;
use steroids\payment\structure\PaymentProcess;

/**
 * Class ManualTestProvider
 * @package steroids\payment\providers
 */
class TestProvider extends BaseProvider
{
    /**
     * @inheritDoc
     */
    public function start(PaymentOrderInterface $order, RequestInfo $request)
    {
        return new PaymentProcess([
            'request' => new RequestInfo([
                'url' => Url::to(['/payment/payment/test'], true),
                'params' => [
                    'orderId' => $order->getId(),
                ]
            ]),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function callback(PaymentOrderInterface $order, RequestInfo $request)
    {
        $order->log('Test callback log message.');
        $order->setExternalId(time());
        $order->setProviderParam('time', time());

        $isOk = isset($request->params['success']);
        if (!$isOk) {
            $order->setErrorMessage('Произошла непредвиденная ошибка.');
        }
        return new PaymentProcess([
            'newStatus' => $isOk ? PaymentStatus::SUCCESS : PaymentStatus::FAILURE,
            'responseText' => $isOk ? 'ok' : 'error',
        ]);
    }

    /**
     * @inheritDoc
     */
    public function resolveOrderId(RequestInfo $request)
    {
        return ArrayHelper::getValue($request->params, 'orderId');
    }

    /**
     * @inheritDoc
     */
    public function resolveErrorMessage(RequestInfo $request)
    {
        return ArrayHelper::getValue($request->params, 'error');
    }
}

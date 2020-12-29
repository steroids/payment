<?php

namespace steroids\payment\providers;

use steroids\payment\models\PaymentOrderInterface;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;
use steroids\core\structure\RequestInfo;
use steroids\payment\structure\PaymentProcess;

/**
 * Class ManualProvider
 * @package steroids\payment\providers
 */
class ManualProvider extends BaseProvider
{
    public ?string $startMessage = null;

    /**
     * @inheritDoc
     */
    public function start(PaymentOrderInterface $order, RequestInfo $request)
    {
        if ($order->isCharge()) {
            return new PaymentProcess([
                'request' => new RequestInfo([
                    'url' => Url::to(['/payment/payment/manual'], true),
                    'params' => [
                        'orderId' => $order->getId(),
                    ]
                ]),
            ]);
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function callback(PaymentOrderInterface $order, RequestInfo $request)
    {
        return new PaymentProcess();
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

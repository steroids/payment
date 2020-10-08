<?php

namespace steroids\payment;

use steroids\core\structure\RequestInfo;
use steroids\payment\models\PaymentOrder;
use steroids\payment\structure\PaymentProcess;
use yii\base\Event;

class PaymentProcessEvent extends Event
{
    /**
     * @var PaymentOrder
     */
    public PaymentOrder $order;

    /**
     * @var RequestInfo
     */
    public RequestInfo $request;

    /**
     * @var PaymentProcess
     */
    public PaymentProcess $process;
}

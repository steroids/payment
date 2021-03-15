<?php


namespace steroids\payment\providers;


use steroids\core\structure\RequestInfo;
use steroids\payment\models\PaymentOrderInterface;
use steroids\payment\structure\PaymentProcess;

class ImpayProdiver extends BaseProvider
{

    public string $merchantKey = 'fN&Z7a94G1K#3QTx5U67K48rXb9!19AXO0542DA';
    /**
     * @inheritDoc
     */
    public function start(PaymentOrderInterface $order, RequestInfo $request)
    {
        // TODO: Implement start() method.
    }

    /**
     * @inheritDoc
     */
    public function callback(PaymentOrderInterface $order, RequestInfo $request)
    {
        // TODO: Implement callback() method.
    }

    /**
     * @inheritDoc
     */
    public function resolveOrderId(RequestInfo $request)
    {
        // TODO: Implement resolveOrderId() method.
    }

    /**
     * @inheritDoc
     */
    public function resolveErrorMessage(RequestInfo $request)
    {
        // TODO: Implement resolveErrorMessage() method.
    }

    
}
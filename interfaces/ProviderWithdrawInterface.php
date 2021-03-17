<?php

namespace steroids\payment\interfaces;

use steroids\core\structure\RequestInfo;
use steroids\payment\models\PaymentOrderInterface;
use steroids\payment\structure\PaymentProcess;

interface ProviderWithdrawInterface
{
    public function withdraw(PaymentOrderInterface $order): PaymentProcess;
}
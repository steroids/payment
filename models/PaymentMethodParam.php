<?php

namespace steroids\payment\models;

use steroids\payment\models\meta\PaymentMethodParamMeta;
use steroids\payment\PaymentModule;

class PaymentMethodParam extends PaymentMethodParamMeta
{
    /**
     * @inheritDoc
     */
    public static function instantiate($row)
    {
        return PaymentModule::instantiateClass(static::class, $row);
    }
}

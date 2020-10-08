<?php

namespace steroids\payment\models;

use steroids\payment\models\meta\PaymentProviderLogMeta;
use steroids\payment\PaymentModule;

class PaymentProviderLog extends PaymentProviderLogMeta
{
    /**
     * @inheritDoc
     */
    public static function instantiate($row)
    {
        return PaymentModule::instantiateClass(static::class, $row);
    }

    public function addLog($message)
    {
        $this->logText .= $message . "\n";
    }
}

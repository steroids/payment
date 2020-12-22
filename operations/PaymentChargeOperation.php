<?php

namespace steroids\payment\operations;

use steroids\billing\operations\BaseBillingOperation;
use steroids\payment\models\PaymentOrder;

/**
 * Class PaymentChargeOperation
 * @property-read PaymentOrder $document
 */
class PaymentChargeOperation extends BaseBillingOperation
{
    /**
     * @return int
     */
    public function getDelta()
    {
        return $this->document->realInAmount ?: $this->document->inAmount;
    }

    public function getTitle()
    {
        if ($this->document->description) {
            return $this->document->description;
        }
        return \Yii::t('app', 'Пополнение через платежную систему');
    }

    public static function getDocumentClass()
    {
        return PaymentOrder::class;
    }
}

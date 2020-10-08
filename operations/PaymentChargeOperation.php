<?php

namespace steroids\payment\operations;

use steroids\billing\operations\BaseOperation;
use steroids\payment\models\PaymentOrder;

/**
 * Class PaymentChargeOperation
 * @property-read PaymentOrder $document
 */
class PaymentChargeOperation extends BaseOperation
{
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

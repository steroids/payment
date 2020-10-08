<?php

namespace steroids\payment\operations;

use steroids\billing\operations\BaseOperation;
use steroids\payment\models\PaymentOrder;

/**
 * Class PaymentWithdrawOperation
 * @property-read PaymentOrder $document
 */
class PaymentWithdrawOperation extends BaseOperation
{
    public function getTitle()
    {
        if ($this->document->description) {
            return $this->document->description;
        }
        return \Yii::t('app', 'Вывод средств');
    }

    public static function getDocumentClass()
    {
        return PaymentOrder::class;
    }
}

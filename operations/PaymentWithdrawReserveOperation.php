<?php

namespace steroids\payment\operations;

use steroids\billing\operations\BaseBillingOperation;
use steroids\payment\models\PaymentOrder;

/**
 * Class PaymentWithdrawReserveOperation
 * @package steroids\payment\operations
 * @property-read PaymentOrder $document
 */
class PaymentWithdrawReserveOperation extends BaseBillingOperation
{
    /**
     * @inheritDoc
     */
    public function getDelta()
    {
        return $this->document->inAmount;
    }

    /**
     * @inheritDoc
     */
    public function getTitle()
    {
        return \Yii::t('app', 'Вывод средств');
    }

    public static function getDocumentClass()
    {
        return PaymentOrder::class;
    }
}
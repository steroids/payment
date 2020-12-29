<?php

namespace steroids\payment\operations;

use steroids\billing\operations\BaseBillingOperation;
use steroids\payment\models\PaymentOrder;

/**
 * Class PaymentWithdrawRollbackOperation
 * @package steroids\payment\operations
 * @property-read PaymentOrder $document
 */
class PaymentWithdrawRollbackOperation extends BaseBillingOperation
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
        return \Yii::t('app', 'Возврат средств (отмена вывода)');
    }

    public static function getDocumentClass()
    {
        return PaymentOrder::class;
    }
}
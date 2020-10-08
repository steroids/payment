<?php

namespace steroids\payment\models;

use steroids\billing\operations\BaseOperation;
use steroids\payment\models\meta\PaymentOrderItemMeta;
use steroids\payment\PaymentModule;
use yii\helpers\Json;

class PaymentOrderItem extends PaymentOrderItemMeta
{
    /**
     * @inheritDoc
     */
    public static function instantiate($row)
    {
        return PaymentModule::instantiateClass(static::class, $row);
    }

    /**
     * @throws \steroids\billing\exceptions\BillingException
     * @throws \steroids\billing\exceptions\InsufficientFundsException
     * @throws \steroids\core\exceptions\ModelSaveException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function execute()
    {
        BaseOperation::createFromArray(Json::decode($this->operationDump))->execute();
    }
}

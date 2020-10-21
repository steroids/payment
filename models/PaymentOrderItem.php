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
        $params = array_merge(
            Json::decode($this->operationDump),
            [
                'payerUserId' => $this->order->payerUserId,
                'documentId' => $this->documentId,
            ]
        );
        if ($this->fromAccountId || $this->toAccountId) {
            $params = array_merge($params, [
                'fromAccountId' => $this->fromAccountId,
                'toAccountId' => $this->toAccountId,
            ]);
        }
        BaseOperation::createFromArray($params)->execute();
    }
}

<?php

namespace steroids\payment\models;

use steroids\billing\models\BillingAccount;
use steroids\payment\exceptions\PaymentException;
use steroids\payment\models\meta\PaymentMethodMeta;
use steroids\payment\PaymentModule;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

class PaymentMethod extends PaymentMethodMeta
{
    private static ?array $_instances = null;

    /**
     * @inheritDoc
     */
    public static function instantiate($row)
    {
        return PaymentModule::instantiateClass(static::class, $row);
    }

    /**
     * @param $name
     * @return static
     */
    public static function getByName($name)
    {
        // Lazy load
        if (!static::$_instances) {
            static::$_instances = [];
            foreach (static::find()->where(['isEnable' => true])->all() as $method) {
                /** @var static $method */
                static::$_instances[$method->name] = $method;
            }
        }

        // Check exists
        if (!isset(static::$_instances[$name])) {
            throw new PaymentException('Not found method by name: ' . $name);
        }

        return static::$_instances[$name];
    }

    /**
     * @param BillingAccount $toAccount
     * @param int $inAmount
     * @param array $params
     * @return PaymentOrder
     */
    public function createOrder(BillingAccount $toAccount, int $inAmount, array $params = [])
    {
        $description = ArrayHelper::remove($params, 'description');
        $redirectUrl = ArrayHelper::remove($params, 'redirectUrl');

        $order = new PaymentOrder([
            'methodId' => $this->primaryKey,
            'methodParamsJson' => !empty($params) ? Json::encode($params) : null,
            'payerAccountId' => $toAccount->primaryKey,
            'inAmount' => $inAmount,
            'inCurrencyCode' => $toAccount->currency->code,
            'description' => $description,
            'redirectUrl' => $redirectUrl,
            'creatorUserId' => !STEROIDS_IS_CLI && \Yii::$app->has('user') ? \Yii::$app->user->id : null,
            'outCurrencyCode' => $this->outCurrencyCode,
            'outCommissionFixed' => $this->outCommissionFixed,
            'outCommissionPercent' => $this->outCommissionPercent,
        ]);
        $order->saveOrPanic();

        $order->populateRelation('method', $this);
        return $order;
    }
}

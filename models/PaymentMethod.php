<?php

namespace steroids\payment\models;

use steroids\billing\models\BillingAccount;
use steroids\billing\models\BillingCurrency;
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
        // Lazy fetch
        static::getAll();

        $model = ArrayHelper::getValue(static::$_instances, $name);
        if (!$model) {
            throw new PaymentException('Not found method by name: ' . $name);
        }

        return $model;
    }

    /**
     * @return BillingCurrency[]
     */
    public static function getAll()
    {
        if (!static::$_instances) {
            static::$_instances = static::find()
                ->where(['isEnable' => true])
                ->indexBy('name')
                ->all();
        }
        return array_values(static::$_instances);
    }

    /**
     * @param BillingAccount $toAccount
     * @param int $inAmount
     * @param array $params
     * @return PaymentOrder
     */
    public function createOrder(int $payerUserId, string $inCurrencyCode, int $inAmount, array $params = [])
    {
        $description = ArrayHelper::remove($params, 'description');
        $redirectUrl = ArrayHelper::remove($params, 'redirectUrl');

        $order = new PaymentOrder([
            'methodId' => $this->primaryKey,
            'methodParamsJson' => !empty($params) ? Json::encode($params) : null,
            'payerUserId' => $payerUserId,
            'inAmount' => $inAmount,
            'inCurrencyCode' => $inCurrencyCode,
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

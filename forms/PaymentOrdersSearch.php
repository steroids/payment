<?php

namespace steroids\payment\forms;

use app\auth\AuthModule;
use app\billing\enums\CurrencyEnum;
use steroids\auth\UserInterface;
use steroids\billing\enums\BillingCurrencyRateDirectionEnum;
use steroids\core\base\Model;
use steroids\payment\enums\PaymentDirection;
use steroids\payment\forms\meta\PaymentOrdersSearchMeta;
use steroids\payment\models\PaymentOrder;
use yii\db\ActiveQuery;
use yii\helpers\Json;
use yii\web\IdentityInterface;

class PaymentOrdersSearch extends PaymentOrdersSearchMeta
{
    /**
     * @var IdentityInterface|UserInterface|Model
     */
    public $user;

    public function fields()
    {
        return [
            'id',
            'description',
            'payerUser',
            'externalId',
            'inAmount' => fn(PaymentOrder $order) => $order->inCurrency->amountToFloat($order->inAmount),
            'inCurrencyCode',
            'outAmount' => fn(PaymentOrder $order) => $order->outCurrency->amountToFloat($order->outAmount),
            'outCurrencyCode',
            'outAmountRub' => function (PaymentOrder $order) {
                return $order->outCurrency->amountToFloat(
                    $order->outCurrency->to(CurrencyEnum::RUB, $order->outAmount, $order->direction)
                );
            },
            'commissionAmountRub' => function (PaymentOrder $order) {
                $commissionAmount = abs($order->outAmount - $order->inCurrency->to(
                        $order->outCurrencyCode,
                        $order->inAmount,
                        $order->direction
                    ));

                return $order->outCurrency->amountToFloat(
                    $order->outCurrency->to(CurrencyEnum::RUB, $commissionAmount, $order->direction)
                );
            },
            'status',
            'createTime',
            'updateTime',
            'errorMessage',
            'method' => [
                'id',
                'title',
            ],
            'methodParams' => fn(PaymentOrder $order) => $order->methodParamsJson ? Json::decode($order->methodParamsJson) : null,
        ];
    }

    /**
     * @param ActiveQuery $query
     */
    public function prepare($query)
    {
        parent::prepare($query);

        $query
            ->with('method')
            ->andFilterWhere([
                'id' => $this->id,
                'status' => $this->status,
                'externalId' => $this->externalId,
            ])
            ->addOrderBy(['id' => SORT_DESC]);

        // Users search
        $likeConditions = [];
        if ($this->payerUserQuery) {
            foreach (AuthModule::getInstance()->loginAvailableAttributes as $attributeType) {
                $attribute = AuthModule::getInstance()->getUserAttributeName($attributeType);
                if ($this->payerUserQuery) {
                    $likeConditions[] = ['like', 'pu.' . $attribute, $this->payerUserQuery];
                }
            }
        }
        if (count($likeConditions) > 0) {
            $query
                ->joinWith('payerUser pu')
                ->andWhere(['or', ...$likeConditions]);
        } else {
            $query->with('payerUser');
        }

        if ($this->user) {
            $query->andWhere(['payerUserId' => $this->user->primaryKey]);
        }
    }
}

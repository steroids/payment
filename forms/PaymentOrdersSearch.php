<?php

namespace steroids\payment\forms;

use app\auth\AuthModule;
use steroids\auth\UserInterface;
use steroids\core\base\Model;
use steroids\payment\forms\meta\PaymentOrdersSearchMeta;
use steroids\payment\models\PaymentOrder;
use yii\db\ActiveQuery;
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
            'inAmount' => fn (PaymentOrder $order) => $order->inCurrency->amountToFloat($order->inAmount),
            'inCurrencyCode',
            'outAmount' => fn (PaymentOrder $order) => $order->outCurrency->amountToFloat($order->outAmount),
            'outCurrencyCode',
            'status',
            'createTime',
            'updateTime',
            'errorMessage',
            'method' => [
                'id',
                'title',
            ],
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

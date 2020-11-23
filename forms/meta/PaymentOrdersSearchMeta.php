<?php

namespace steroids\payment\forms\meta;

use steroids\core\base\SearchModel;
use steroids\payment\enums\PaymentStatus;
use \Yii;
use steroids\payment\models\PaymentOrder;

abstract class PaymentOrdersSearchMeta extends SearchModel
{
    public ?int $id = null;
    public ?string $status = null;
    public ?string $payerUserQuery = null;
    public ?string $externalId = null;

    public function rules()
    {
        return [
            ...parent::rules(),
            ['status', 'in', 'range' => PaymentStatus::getKeys()],
            [['payerUserQuery', 'externalId'], 'string', 'max' => 255],
        ];
    }

    public function sortFields()
    {
        return [
            'id',
            'status'
        ];
    }

    public function createQuery()
    {
        return PaymentOrder::find();
    }

    public static function meta()
    {
        return [
            'id' => [
                'label' => Yii::t('steroids', 'ID'),
                'appType' => 'primaryKey',
                'isSortable' => true
            ],
            'status' => [
                'label' => Yii::t('steroids', 'Статус'),
                'appType' => 'enum',
                'isSortable' => true,
                'enumClassName' => PaymentStatus::class
            ],
            'payerUserQuery' => [
                'label' => Yii::t('steroids', 'Пользователь'),
                'isSortable' => false
            ],
            'externalId' => [
                'label' => Yii::t('steroids', 'Внешний ИД'),
                'isSortable' => false
            ]
        ];
    }
}

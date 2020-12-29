<?php

namespace steroids\payment\models\meta;

use steroids\core\base\Model;
use steroids\payment\enums\PaymentStatus;
use \Yii;
use yii\db\ActiveQuery;
use steroids\payment\models\PaymentOrder;

/**
 * @property string $id
 * @property integer $orderId
 * @property string $operationDump
 * @property integer $position
 * @property integer $documentId
 * @property integer $fromAccountId
 * @property integer $toAccountId
 * @property string $conditionStatus
 * @property-read PaymentOrder $order
 */
abstract class PaymentOrderItemMeta extends Model
{
    public static function tableName()
    {
        return 'payment_order_items';
    }

    public function fields()
    {
        return [
        ];
    }

    public function rules()
    {
        return [
            ...parent::rules(),
            [['orderId', 'position', 'documentId', 'fromAccountId', 'toAccountId'], 'integer'],
            ['operationDump', 'string'],
            ['conditionStatus', 'in', 'range' => PaymentStatus::getKeys()],
        ];
    }

    /**
     * @return ActiveQuery
     */
    public function getOrder()
    {
        return $this->hasOne(PaymentOrder::class, ['id' => 'orderId']);
    }

    public static function meta()
    {
        return array_merge(parent::meta(), [
            'id' => [
                'label' => Yii::t('steroids', 'ID'),
                'appType' => 'primaryKey',
                'isPublishToFrontend' => false
            ],
            'orderId' => [
                'label' => Yii::t('steroids', 'Ордер'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ],
            'operationDump' => [
                'label' => Yii::t('steroids', 'Дамп операции для выполнения'),
                'appType' => 'text',
                'isPublishToFrontend' => false
            ],
            'position' => [
                'label' => Yii::t('steroids', 'Порядок'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ],
            'documentId' => [
                'label' => Yii::t('steroids', 'ИД документа'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ],
            'fromAccountId' => [
                'label' => Yii::t('steroids', 'Источник'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ],
            'toAccountId' => [
                'label' => Yii::t('steroids', 'Получатель'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ],
            'conditionStatus' => [
                'label' => Yii::t('steroids', 'Выполнять при статусе'),
                'appType' => 'enum',
                'isPublishToFrontend' => false,
                'enumClassName' => PaymentStatus::class
            ]
        ]);
    }
}

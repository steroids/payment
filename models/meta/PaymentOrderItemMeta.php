<?php

namespace steroids\payment\models\meta;

use steroids\core\base\Model;
use \Yii;

/**
 * @property string $id
 * @property integer $orderId
 * @property string $operationDump
 * @property integer $position
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
            [['orderId', 'position'], 'integer'],
            ['operationDump', 'string'],
        ];
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
            ]
        ]);
    }
}

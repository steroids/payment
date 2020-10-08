<?php

namespace steroids\payment\models\meta;

use steroids\core\base\Model;
use \Yii;

/**
 * @property string $id
 * @property string $providerName
 * @property integer $orderId
 * @property integer $methodId
 * @property string $requestRaw
 * @property string $responseRaw
 * @property string $errorRaw
 * @property string $startTime
 * @property string $endTime
 * @property string $logText
 * @property string $callMethod
 */
abstract class PaymentProviderLogMeta extends Model
{
    public static function tableName()
    {
        return 'payment_provider_logs';
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
            [['providerName', 'callMethod'], 'string', 'max' => 255],
            ['providerName', 'required'],
            [['orderId', 'methodId'], 'integer'],
            [['requestRaw', 'responseRaw', 'errorRaw', 'logText'], 'string'],
            [['startTime', 'endTime'], 'date', 'format' => 'php:Y-m-d H:i:s'],
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
            'providerName' => [
                'label' => Yii::t('steroids', 'Название платежного шлюза'),
                'isRequired' => true,
                'isPublishToFrontend' => false
            ],
            'orderId' => [
                'label' => Yii::t('steroids', 'Ордер'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ],
            'methodId' => [
                'label' => Yii::t('steroids', 'Метод'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ],
            'requestRaw' => [
                'label' => Yii::t('steroids', 'HTTP запрос'),
                'appType' => 'text',
                'isPublishToFrontend' => false
            ],
            'responseRaw' => [
                'label' => Yii::t('steroids', 'HTTP ответ'),
                'appType' => 'text',
                'isPublishToFrontend' => false
            ],
            'errorRaw' => [
                'label' => Yii::t('steroids', 'Текст ошибки'),
                'appType' => 'text',
                'isPublishToFrontend' => false
            ],
            'startTime' => [
                'label' => Yii::t('steroids', 'Время начала'),
                'appType' => 'dateTime',
                'isPublishToFrontend' => false
            ],
            'endTime' => [
                'label' => Yii::t('steroids', 'Время завершения'),
                'appType' => 'dateTime',
                'isPublishToFrontend' => false
            ],
            'logText' => [
                'label' => Yii::t('steroids', 'Текст лога'),
                'appType' => 'text',
                'isPublishToFrontend' => false
            ],
            'callMethod' => [
                'label' => Yii::t('steroids', 'Вызываемый метод провайдера'),
                'isPublishToFrontend' => false
            ]
        ]);
    }
}

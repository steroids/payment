<?php

namespace steroids\payment\models\meta;

use steroids\core\base\Model;
use steroids\payment\enums\PaymentStatus;
use steroids\core\behaviors\TimestampBehavior;
use \Yii;
use yii\db\ActiveQuery;
use steroids\payment\models\PaymentMethod;
use steroids\billing\models\BillingCurrency;
use steroids\payment\models\PaymentOrderItem;

/**
 * @property string $id
 * @property string $uid
 * @property string $description
 * @property integer $methodId
 * @property integer $creatorUserId
 * @property integer $payerUserId
 * @property string $providerParamsJson
 * @property string $externalId
 * @property integer $inAmount
 * @property string $inCurrencyCode
 * @property integer $outCommissionFixed
 * @property string $outCommissionPercent
 * @property integer $outAmount
 * @property string $outCurrencyCode
 * @property string $status
 * @property string $redirectUrl
 * @property string $createTime
 * @property string $updateTime
 * @property string $methodParamsJson
 * @property string $errorMessage
 * @property integer $realInAmount
 * @property integer $realOutAmount
 * @property string $outCommissionCurrencyCode
 * @property integer $rateUsd
 * @property-read PaymentMethod $method
 * @property-read BillingCurrency $inCurrency
 * @property-read BillingCurrency $outCurrency
 * @property-read PaymentOrderItem[] $items
 */
abstract class PaymentOrderMeta extends Model
{
    public static function tableName()
    {
        return 'payment_orders';
    }

    public function fields()
    {
        return [
            'id',
            'description',
            'payerUserId',
            'inAmount',
            'inCurrencyCode',
            'outAmount',
            'outCurrencyCode',
            'status',
        ];
    }

    public function rules()
    {
        return [
            ...parent::rules(),
            ['uid', 'string', 'max' => '36'],
            [['description', 'externalId', 'inCurrencyCode', 'outCurrencyCode', 'errorMessage', 'outCommissionCurrencyCode'], 'string', 'max' => 255],
            [['methodId', 'creatorUserId', 'payerUserId', 'inAmount', 'outCommissionFixed', 'outAmount', 'realInAmount', 'realOutAmount', 'rateUsd'], 'integer'],
            [['providerParamsJson', 'redirectUrl', 'methodParamsJson'], 'string'],
            ['outCommissionPercent', 'number'],
            ['status', 'in', 'range' => PaymentStatus::getKeys()],
        ];
    }

    public function behaviors()
    {
        return [
            ...parent::behaviors(),
            TimestampBehavior::class,
        ];
    }

    /**
     * @return ActiveQuery
     */
    public function getMethod()
    {
        return $this->hasOne(PaymentMethod::class, ['id' => 'methodId']);
    }

    /**
     * @return ActiveQuery
     */
    public function getInCurrency()
    {
        return $this->hasOne(BillingCurrency::class, ['code' => 'inCurrencyCode']);
    }

    /**
     * @return ActiveQuery
     */
    public function getOutCurrency()
    {
        return $this->hasOne(BillingCurrency::class, ['code' => 'outCurrencyCode']);
    }

    /**
     * @return ActiveQuery
     */
    public function getItems()
    {
        return $this->hasMany(PaymentOrderItem::class, ['orderId' => 'id']);
    }

    public static function meta()
    {
        return array_merge(parent::meta(), [
            'id' => [
                'label' => Yii::t('steroids', 'ID'),
                'appType' => 'primaryKey',
                'isPublishToFrontend' => true
            ],
            'uid' => [
                'label' => Yii::t('steroids', 'Uid'),
                'isPublishToFrontend' => false,
                'stringLength' => '36'
            ],
            'description' => [
                'label' => Yii::t('steroids', 'Описание'),
                'isPublishToFrontend' => true
            ],
            'methodId' => [
                'label' => Yii::t('steroids', 'Метод'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ],
            'creatorUserId' => [
                'label' => Yii::t('steroids', 'Платеж создал'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ],
            'payerUserId' => [
                'label' => Yii::t('steroids', 'Кто оплачивает'),
                'appType' => 'integer',
                'isPublishToFrontend' => true
            ],
            'providerParamsJson' => [
                'label' => Yii::t('steroids', 'Промежуточные параметры провайдера'),
                'appType' => 'text',
                'isPublishToFrontend' => false
            ],
            'externalId' => [
                'label' => Yii::t('steroids', 'Внешний ИД'),
                'isPublishToFrontend' => false
            ],
            'inAmount' => [
                'label' => Yii::t('steroids', 'Сумма в валюте сайта'),
                'appType' => 'integer',
                'isPublishToFrontend' => true
            ],
            'inCurrencyCode' => [
                'label' => Yii::t('steroids', 'Валюта сайта'),
                'isPublishToFrontend' => true
            ],
            'outCommissionFixed' => [
                'label' => Yii::t('steroids', 'Комиссия в валюте платежной системы'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ],
            'outCommissionPercent' => [
                'label' => Yii::t('steroids', 'Комиссия в %'),
                'appType' => 'double',
                'isPublishToFrontend' => false,
                'scale' => '2'
            ],
            'outAmount' => [
                'label' => Yii::t('steroids', 'Сумма в валюте платежного шлюза'),
                'appType' => 'integer',
                'isPublishToFrontend' => true
            ],
            'outCurrencyCode' => [
                'label' => Yii::t('steroids', 'Валюта платежного шлюза'),
                'isPublishToFrontend' => true
            ],
            'status' => [
                'label' => Yii::t('steroids', 'Статус'),
                'appType' => 'enum',
                'isPublishToFrontend' => true,
                'enumClassName' => PaymentStatus::class
            ],
            'redirectUrl' => [
                'label' => Yii::t('steroids', 'Ссылка для редиректа'),
                'appType' => 'text',
                'isPublishToFrontend' => false
            ],
            'createTime' => [
                'label' => Yii::t('steroids', 'Создан'),
                'appType' => 'autoTime',
                'isPublishToFrontend' => false,
                'touchOnUpdate' => false
            ],
            'updateTime' => [
                'label' => Yii::t('steroids', 'Обновлен'),
                'appType' => 'autoTime',
                'isPublishToFrontend' => false,
                'touchOnUpdate' => true
            ],
            'methodParamsJson' => [
                'label' => Yii::t('steroids', 'Данные, специфичные для данного метода оплаты'),
                'appType' => 'text',
                'isPublishToFrontend' => false
            ],
            'errorMessage' => [
                'label' => Yii::t('steroids', 'Текст ошибки, полученный от платежной системы'),
                'isPublishToFrontend' => false
            ],
            'realInAmount' => [
                'label' => Yii::t('steroids', 'Сумма в валюте сайта (фактически зачисляемая)'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ],
            'realOutAmount' => [
                'label' => Yii::t('steroids', 'Сумма в валюте платежного шлюза (фактически зачисляемая)'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ],
            'outCommissionCurrencyCode' => [
                'label' => Yii::t('steroids', 'Валюта outCommissionFixed'),
                'isPublishToFrontend' => false
            ],
            'rateUsd' => [
                'label' => Yii::t('steroids', 'курс валюты на момент операции'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ]
        ]);
    }
}

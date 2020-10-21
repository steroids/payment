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
 * @property integer $outCommissionPercent
 * @property integer $outAmount
 * @property string $outCurrencyCode
 * @property string $status
 * @property string $redirectUrl
 * @property string $createTime
 * @property string $updateTime
 * @property string $methodParamsJson
 * @property string $errorMessage
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
        ];
    }

    public function rules()
    {
        return [
            ...parent::rules(),
            ['uid', 'string', 'max' => '36'],
            [['description', 'externalId', 'inCurrencyCode', 'outCurrencyCode', 'errorMessage'], 'string', 'max' => 255],
            [['methodId', 'creatorUserId', 'payerUserId', 'inAmount', 'outCommissionFixed', 'outCommissionPercent', 'outAmount'], 'integer'],
            [['providerParamsJson', 'redirectUrl', 'methodParamsJson'], 'string'],
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
                'isPublishToFrontend' => false
            ],
            'uid' => [
                'label' => Yii::t('steroids', 'Uid'),
                'isPublishToFrontend' => false,
                'stringLength' => '36'
            ],
            'description' => [
                'label' => Yii::t('steroids', 'Описание'),
                'isPublishToFrontend' => false
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
                'isPublishToFrontend' => false
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
                'isPublishToFrontend' => false
            ],
            'inCurrencyCode' => [
                'label' => Yii::t('steroids', 'Валюта сайта'),
                'isPublishToFrontend' => false
            ],
            'outCommissionFixed' => [
                'label' => Yii::t('steroids', 'Комиссия в валюте платежной системы'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ],
            'outCommissionPercent' => [
                'label' => Yii::t('steroids', 'Комиссия в %'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ],
            'outAmount' => [
                'label' => Yii::t('steroids', 'Сумма в валюте платежного шлюза'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ],
            'outCurrencyCode' => [
                'label' => Yii::t('steroids', 'Валюта платежного шлюза'),
                'isPublishToFrontend' => false
            ],
            'status' => [
                'label' => Yii::t('steroids', 'Статус'),
                'appType' => 'enum',
                'isPublishToFrontend' => false,
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
            ]
        ]);
    }
}

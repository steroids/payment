<?php

namespace steroids\payment\models\meta;

use steroids\core\base\Model;
use steroids\payment\enums\PaymentDirection;
use steroids\core\behaviors\TimestampBehavior;
use \Yii;
use yii\db\ActiveQuery;
use steroids\billing\models\BillingCurrency;
use steroids\payment\models\PaymentMethodParam;
use steroids\billing\models\BillingAccount;

/**
 * @property string $id
 * @property string $providerName
 * @property string $direction
 * @property integer $outCommissionFixed
 * @property string $outCommissionPercent
 * @property boolean $isEnable
 * @property string $createTime
 * @property string $updateTime
 * @property string $name
 * @property string $outCurrencyCode
 * @property integer $systemAccountId
 * @property string $title
 * @property string $outCommissionCurrencyCode
 * @property-read BillingCurrency $outCurrency
 * @property-read PaymentMethodParam[] $params
 * @property-read BillingAccount $systemAccount
 */
abstract class PaymentMethodMeta extends Model
{
    public static function tableName()
    {
        return 'payment_methods';
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
            [['providerName', 'name', 'outCurrencyCode', 'title', 'outCommissionCurrencyCode'], 'string', 'max' => 255],
            ['direction', 'in', 'range' => PaymentDirection::getKeys()],
            [['outCommissionFixed', 'systemAccountId'], 'integer'],
            ['outCommissionPercent', 'number'],
            ['isEnable', 'steroids\\core\\validators\\ExtBooleanValidator'],
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
    public function getOutCurrency()
    {
        return $this->hasOne(BillingCurrency::class, ['code' => 'outCurrencyCode']);
    }

    /**
     * @return ActiveQuery
     */
    public function getParams()
    {
        return $this->hasMany(PaymentMethodParam::class, ['methodId' => 'id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getSystemAccount()
    {
        return $this->hasOne(BillingAccount::class, ['id' => 'systemAccountId']);
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
                'isPublishToFrontend' => false
            ],
            'direction' => [
                'label' => Yii::t('steroids', 'Направление платежа (пополнение или вывод)'),
                'appType' => 'enum',
                'isPublishToFrontend' => false,
                'enumClassName' => PaymentDirection::class
            ],
            'outCommissionFixed' => [
                'label' => Yii::t('steroids', 'Комиссия в валюте платежного шлюза'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ],
            'outCommissionPercent' => [
                'label' => Yii::t('steroids', 'Комиссия в %'),
                'appType' => 'double',
                'isPublishToFrontend' => false
            ],
            'isEnable' => [
                'label' => Yii::t('steroids', 'Включен?'),
                'appType' => 'boolean',
                'isPublishToFrontend' => false
            ],
            'createTime' => [
                'label' => Yii::t('steroids', 'Добавлен'),
                'appType' => 'autoTime',
                'isPublishToFrontend' => false,
                'touchOnUpdate' => false
            ],
            'updateTime' => [
                'label' => Yii::t('steroids', 'Обновлен'),
                'appType' => 'autoTime',
                'isPublishToFrontend' => false,
                'touchOnUpdate' => false
            ],
            'name' => [
                'label' => Yii::t('steroids', 'Системное имя (латиницей)'),
                'isPublishToFrontend' => false
            ],
            'outCurrencyCode' => [
                'label' => Yii::t('steroids', 'Валюта платежной системы'),
                'isPublishToFrontend' => false
            ],
            'systemAccountId' => [
                'label' => Yii::t('steroids', 'Системный аккаунт для списания'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ],
            'title' => [
                'label' => Yii::t('steroids', 'Название'),
                'isPublishToFrontend' => false
            ],
            'outCommissionCurrencyCode' => [
                'label' => Yii::t('steroids', 'Валюта outCommissionFixed '),
                'isPublishToFrontend' => false
            ]
        ]);
    }
}

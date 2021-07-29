<?php

namespace steroids\payment\models\meta;

use steroids\core\base\Model;
use steroids\core\behaviors\TimestampBehavior;
use \Yii;

/**
 * @property string $id
 * @property string $name
 * @property string $type
 * @property string $typeValues
 * @property boolean $isRequired
 * @property integer $min
 * @property integer $max
 * @property string $label
 * @property string $createTime
 * @property string $updateTime
 * @property integer $methodId
 * @property boolean $isVisible
 */
abstract class PaymentMethodParamMeta extends Model
{
    public static function tableName()
    {
        return 'payment_method_params';
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
            [['name', 'type', 'label'], 'string', 'max' => 255],
            ['name', 'required'],
            ['typeValues', 'string'],
            [['isRequired', 'isVisible'], 'steroids\\core\\validators\\ExtBooleanValidator'],
            [['min', 'max', 'methodId'], 'integer'],
        ];
    }

    public function behaviors()
    {
        return [
            ...parent::behaviors(),
            TimestampBehavior::class,
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
            'name' => [
                'label' => Yii::t('steroids', 'Имя параметра (латиницей)'),
                'isRequired' => true,
                'isPublishToFrontend' => false
            ],
            'type' => [
                'label' => Yii::t('steroids', 'Тип'),
                'isPublishToFrontend' => false
            ],
            'typeValues' => [
                'label' => Yii::t('steroids', 'Доступные значения через запятую (для enum)'),
                'appType' => 'text',
                'isPublishToFrontend' => false
            ],
            'isRequired' => [
                'label' => Yii::t('steroids', 'Обязателен?'),
                'appType' => 'boolean',
                'isPublishToFrontend' => false
            ],
            'min' => [
                'label' => Yii::t('steroids', 'Минимальное значение/длина'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ],
            'max' => [
                'label' => Yii::t('steroids', 'Максимальное значение/длина'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ],
            'label' => [
                'label' => Yii::t('steroids', 'Название'),
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
                'touchOnUpdate' => true
            ],
            'methodId' => [
                'label' => Yii::t('steroids', 'Метод'),
                'appType' => 'integer',
                'isPublishToFrontend' => false
            ],
            'isVisible' => [
                'label' => Yii::t('steroids', 'Отображать на сайте?'),
                'appType' => 'boolean',
                'defaultValue' => true
            ]
        ]);
    }
}

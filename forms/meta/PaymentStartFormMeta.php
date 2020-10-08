<?php

namespace steroids\payment\forms\meta;

use steroids\core\base\FormModel;
use \Yii;

abstract class PaymentStartFormMeta extends FormModel
{
    public ?float $inAmount = null;
    public ?string $methodName = null;
    public ?string $accountName = null;
    public ?string $currencyCode = null;


    public function rules()
    {
        return [
            ...parent::rules(),
            ['inAmount', 'number'],
            [['inAmount', 'methodName', 'accountName', 'currencyCode'], 'required'],
            [['methodName', 'accountName', 'currencyCode'], 'string', 'max' => 255],
        ];
    }

    public static function meta()
    {
        return [
            'inAmount' => [
                'label' => Yii::t('steroids', 'Сумма'),
                'appType' => 'double',
                'isRequired' => true,
                'isSortable' => false,
                'scale' => '2'
            ],
            'methodName' => [
                'label' => Yii::t('steroids', 'Способ платежа'),
                'isRequired' => true,
                'isSortable' => false
            ],
            'accountName' => [
                'label' => Yii::t('steroids', 'Название аккаунта'),
                'isRequired' => true,
                'isSortable' => false
            ],
            'currencyCode' => [
                'label' => Yii::t('steroids', 'Валюта'),
                'isRequired' => true,
                'isSortable' => false
            ]
        ];
    }
}

<?php

namespace steroids\payment\enums\meta;

use Yii;
use steroids\core\base\Enum;

abstract class PaymentMethodEnumMeta extends Enum
{
    const START = 'start';
    const WITHDRAW = 'withdraw';

    public static function getLabels()
    {
        return [
            self::START => Yii::t('app', 'Зачисление'),
            self::WITHDRAW => Yii::t('app', 'Списание')
        ];
    }
}

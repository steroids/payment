<?php

namespace steroids\payment\enums\meta;

use Yii;
use steroids\core\base\Enum;

abstract class PaymentDirectionMeta extends Enum
{
    const CHARGE = 'charge';
    const WITHDRAW = 'withdraw';

    public static function getLabels()
    {
        return [
            self::CHARGE => Yii::t('app', 'Пополнение'),
            self::WITHDRAW => Yii::t('app', 'Вывод')
        ];
    }
}

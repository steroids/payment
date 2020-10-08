<?php

namespace steroids\payment\enums\meta;

use Yii;
use steroids\core\base\Enum;

abstract class PaymentStatusMeta extends Enum
{
    const CREATED = 'created';
    const PROCESS = 'process';
    const SUCCESS = 'success';
    const FAILURE = 'failure';

    public static function getLabels()
    {
        return [
            self::CREATED => Yii::t('app', 'Создан'),
            self::PROCESS => Yii::t('app', 'В процессе'),
            self::SUCCESS => Yii::t('app', 'Успешно выполнен'),
            self::FAILURE => Yii::t('app', 'Произошла ошибка')
        ];
    }
}

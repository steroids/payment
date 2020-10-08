<?php

namespace steroids\payment\enums;

use steroids\payment\enums\meta\PaymentStatusMeta;

class PaymentStatus extends PaymentStatusMeta
{
    public static function getFinishStatuses()
    {
        return [
            static::SUCCESS,
            static::FAILURE,
        ];
    }
}

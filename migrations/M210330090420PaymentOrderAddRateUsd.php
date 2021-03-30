<?php

namespace steroids\payment\migrations;

use steroids\core\base\Migration;

class M210330090420PaymentOrderAddRateUsd extends Migration
{
    public function safeUp()
    {
        $this->addColumn('payment_orders', 'rateUsd', $this->integer());
    }

    public function safeDown()
    {
        $this->dropColumn('payment_orders', 'rateUsd');
    }
}

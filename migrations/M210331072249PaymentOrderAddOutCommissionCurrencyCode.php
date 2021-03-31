<?php

namespace steroids\payment\migrations;

use steroids\core\base\Migration;

class M210331072249PaymentOrderAddOutCommissionCurrencyCode extends Migration
{
    public function safeUp()
    {
        $this->addColumn('payment_orders', 'outCommissionCurrencyCode', $this->string());
    }

    public function safeDown()
    {
        $this->dropColumn('payment_orders', 'outCommissionCurrencyCode');
    }
}

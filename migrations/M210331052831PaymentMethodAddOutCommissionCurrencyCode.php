<?php

namespace steroids\payment\migrations;

use steroids\core\base\Migration;

class M210331052831PaymentMethodAddOutCommissionCurrencyCode extends Migration
{
    public function safeUp()
    {
        $this->addColumn('payment_methods', 'outCommissionCurrencyCode', $this->string());
    }

    public function safeDown()
    {
        $this->dropColumn('payment_methods', 'outCommissionCurrencyCode');
    }
}

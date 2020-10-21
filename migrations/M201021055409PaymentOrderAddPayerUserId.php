<?php

namespace steroids\payment\migrations;

use steroids\core\base\Migration;

class M201021055409PaymentOrderAddPayerUserId extends Migration
{
    public function safeUp()
    {
        $this->addColumn('payment_orders', 'payerUserId', $this->integer());
        $this->dropColumn('payment_orders', 'payerAccountId');
    }

    public function safeDown()
    {
        $this->dropColumn('payment_orders', 'payerUserId');
        $this->addColumn('payment_orders', 'payerAccountId', $this->integer());
    }
}

<?php

namespace steroids\payment\migrations;

use steroids\core\base\Migration;

class M201222082338PaymentOrderAddRealInAmountRealOutAmount extends Migration
{
    public function safeUp()
    {
        $this->addColumn('payment_orders', 'realInAmount', $this->integer());
        $this->addColumn('payment_orders', 'realOutAmount', $this->integer());
    }

    public function safeDown()
    {
        $this->dropColumn('payment_orders', 'realInAmount');
        $this->dropColumn('payment_orders', 'realOutAmount');
    }
}

<?php

namespace steroids\payment\migrations;

use steroids\core\base\Migration;

class M201229023253PaymentOrderItemAddConditionStatus extends Migration
{
    public function safeUp()
    {
        $this->addColumn('payment_order_items', 'conditionStatus', $this->string());
    }

    public function safeDown()
    {
        $this->dropColumn('payment_order_items', 'conditionStatus');
    }
}

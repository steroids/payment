<?php

namespace steroids\payment\migrations;

use yii\db\Query;
use steroids\core\base\Migration;

class M210330095817PaymentOrderUpdOutCommissionPercent extends Migration
{
    public function safeUp()
    {
        $this->alterColumn('payment_orders', 'outCommissionPercent', $this->decimal(19,2));
    }

    public function safeDown()
    {
        $this->alterColumn('payment_orders', 'outCommissionPercent', $this->integer());
    }
}

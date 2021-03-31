<?php

namespace steroids\payment\migrations;

use steroids\core\base\Migration;
use yii\db\Query;

class M210330103409PaymentMethodUpdOutCommissionPercent extends Migration
{
    public function safeUp()
    {
        $this->alterColumn('payment_methods', 'outCommissionPercent', $this->decimal(19,2));
    }

    public function safeDown()
    {
        $this->alterColumn('payment_methods', 'outCommissionPercent', $this->integer());
    }
}

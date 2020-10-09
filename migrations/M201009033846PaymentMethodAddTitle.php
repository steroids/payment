<?php

namespace steroids\payment\migrations;

use steroids\core\base\Migration;

class M201009033846PaymentMethodAddTitle extends Migration
{
    public function safeUp()
    {
        $this->addColumn('payment_methods', 'title', $this->string());
    }

    public function safeDown()
    {
        $this->dropColumn('payment_methods', 'title');
    }
}

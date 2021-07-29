<?php

namespace steroids\payment\migrations;

use steroids\core\base\Migration;

class M210720104752PaymentMethodParamAddIsVisible extends Migration
{
    public function safeUp()
    {
        $this->addColumn('payment_method_params', 'isVisible', $this->boolean()->defaultValue(true));
    }

    public function safeDown()
    {
        $this->dropColumn('payment_method_params', 'isVisible');
    }
}
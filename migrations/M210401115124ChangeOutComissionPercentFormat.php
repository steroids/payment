<?php

namespace steroids\payment\migrations;

use steroids\core\base\Migration;

/**
 * Class M210401115124ChangeOutComissionPercentFormat
 */
class M210401115124ChangeOutComissionPercentFormat extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->alterColumn('payment_orders', 'outCommissionPercent', $this->decimal(3,2));
        $this->alterColumn('payment_methods', 'outCommissionPercent', $this->decimal(3,2));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->alterColumn('payment_orders', 'outCommissionPercent', $this->decimal(19,2));
        $this->alterColumn('payment_methods', 'outCommissionPercent', $this->decimal(19,2));
    }
}

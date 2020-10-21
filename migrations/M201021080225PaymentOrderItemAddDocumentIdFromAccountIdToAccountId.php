<?php

namespace steroids\payment\migrations;

use steroids\core\base\Migration;

class M201021080225PaymentOrderItemAddDocumentIdFromAccountIdToAccountId extends Migration
{
    public function safeUp()
    {
        $this->addColumn('payment_order_items', 'documentId', $this->integer());
        $this->addColumn('payment_order_items', 'fromAccountId', $this->integer());
        $this->addColumn('payment_order_items', 'toAccountId', $this->integer());
    }

    public function safeDown()
    {
        $this->dropColumn('payment_order_items', 'documentId');
        $this->dropColumn('payment_order_items', 'fromAccountId');
        $this->dropColumn('payment_order_items', 'toAccountId');
    }
}

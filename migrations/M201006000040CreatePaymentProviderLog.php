<?php

namespace steroids\payment\migrations;

use steroids\core\base\Migration;

class M201006000040CreatePaymentProviderLog extends Migration
{
    public function safeUp()
    {
        $this->createTable('payment_provider_logs', [
            'id' => $this->primaryKey(),
            'providerName' => $this->string()->notNull(),
            'orderId' => $this->integer(),
            'methodId' => $this->integer(),
            'requestRaw' => $this->text(),
            'responseRaw' => $this->text(),
            'errorRaw' => $this->text(),
            'startTime' => $this->dateTime(),
            'endTime' => $this->dateTime(),
            'logText' => $this->text(),
            'callMethod' => $this->string(),
        ]);
    }

    public function safeDown()
    {
        $this->dropTable('payment_provider_logs');
    }
}

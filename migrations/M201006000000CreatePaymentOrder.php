<?php

namespace app\payment\migrations;

use steroids\core\base\Migration;

class M201006000000CreatePaymentOrder extends Migration
{
    public function safeUp()
    {
        $this->createTable('payment_orders', [
            'id' => $this->primaryKey(),
            'uid' => $this->string(36),
            'description' => $this->string(),
            'methodId' => $this->integer(),
            'creatorUserId' => $this->integer(),
            'payerAccountId' => $this->integer(),
            'providerParamsJson' => $this->text(),
            'externalId' => $this->string(),
            'inAmount' => $this->bigInteger(),
            'inCurrencyCode' => $this->string(),
            'commissionFixed' => $this->bigInteger(),
            'commissionPercent' => $this->integer(),
            'outAmount' => $this->bigInteger(),
            'outCurrencyCode' => $this->integer(),
            'status' => $this->string(),
            'redirectUrl' => $this->text(),
            'createTime' => $this->dateTime(),
            'updateTime' => $this->dateTime(),
            'methodParamsJson' => $this->text(),
            'errorMessage' => $this->string(),
        ]);
        $this->createTable('payment_order_items', [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer(),
            'operationDump' => $this->text(),
            'position' => $this->integer(),
        ]);

        $this->createForeignKey('payment_orders', 'inCurrencyCode', 'billing_currencies', 'code');
        $this->createForeignKey('payment_orders', 'outCurrencyCode', 'billing_currencies', 'code');
        $this->createForeignKey('payment_orders', 'payerAccountId', 'billing_accounts', 'id');

    }

    public function safeDown()
    {
        $this->deleteForeignKey('payment_orders', 'inCurrencyCode', 'billing_currencies', 'code');
        $this->deleteForeignKey('payment_orders', 'outCurrencyCode', 'billing_currencies', 'code');
        $this->deleteForeignKey('payment_orders', 'payerAccountId', 'billing_accounts', 'id');

        $this->dropTable('payment_order_items');
        $this->dropTable('payment_orders');
    }
}

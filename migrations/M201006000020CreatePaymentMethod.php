<?php

namespace app\payment\migrations;

use steroids\core\base\Migration;

class M201006000020CreatePaymentMethod extends Migration
{
    public function safeUp()
    {
        $this->createTable('payment_methods', [
            'id' => $this->primaryKey(),
            'providerName' => $this->string(),
            'direction' => $this->string(),
            'outCommissionFixed' => $this->bigInteger(),
            'outCommissionPercent' => $this->integer(),
            'isEnable' => $this->boolean()->notNull()->defaultValue(0),
            'name' => $this->string(),
            'outCurrencyCode' => $this->string(),
            'createTime' => $this->dateTime(),
            'updateTime' => $this->dateTime(),
            'systemAccountId' => $this->integer(),
        ]);

        $this->createTable('payment_method_params', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'type' => $this->string(),
            'typeValues' => $this->text(),
            'isRequired' => $this->boolean()->notNull()->defaultValue(0),
            'min' => $this->integer(),
            'max' => $this->integer(),
            'label' => $this->string(),
            'createTime' => $this->dateTime(),
            'updateTime' => $this->dateTime(),
            'methodId' => $this->integer(),
        ]);

        $this->createForeignKey('payment_methods', 'outCurrencyCode', 'billing_currencies', 'code');
        $this->createForeignKey('payment_methods', 'systemAccountId', 'billing_accounts', 'id');
        $this->createForeignKey('payment_orders', 'methodId', 'payment_methods', 'id');
    }

    public function safeDown()
    {
        $this->deleteForeignKey('payment_methods', 'outCurrencyCode', 'billing_currencies', 'code');
        $this->deleteForeignKey('payment_methods', 'systemAccountId', 'billing_accounts', 'id');
        $this->deleteForeignKey('payment_orders', 'methodId', 'payment_methods', 'id');

        $this->dropTable('payment_method_params');
        $this->dropTable('payment_methods');
    }
}

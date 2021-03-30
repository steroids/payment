<?php

namespace steroids\payment\migrations;

use yii\db\Query;
use steroids\core\base\Migration;

class M210330095817PaymentOrderUpdOutCommissionPercent extends Migration
{
    public function safeUp()
    {
        $this->alterColumn('payment_orders', 'outCommissionPercent', $this->decimal(19,2));

        $orders = (new Query())
            ->from('payment_orders')
            ->all();

        foreach ($orders as $order){
            if($order['outCommissionPercent'] === 0 || !$order['outCommissionPercent']){
                continue;
            }
            $this->update('payment_orders', ['outCommissionPercent' => $order['outCommissionPercent'] / 100], ['id' => $order['id']]);
        }
    }

    public function safeDown()
    {
        $orders = (new Query())
            ->from('payment_orders')
            ->all();

        foreach ($orders as $order){
            if($order['outCommissionPercent'] === 0 || !$order['outCommissionPercent']){
                continue;
            }
            $this->update('payment_orders', ['outCommissionPercent' => $order['outCommissionPercent'] * 100], ['id' => $order['id']]);
        }

        $this->alterColumn('payment_orders', 'outCommissionPercent', $this->integer());
    }
}

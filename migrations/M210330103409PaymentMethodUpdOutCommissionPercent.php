<?php

namespace steroids\payment\migrations;

use steroids\core\base\Migration;
use yii\db\Query;

class M210330103409PaymentMethodUpdOutCommissionPercent extends Migration
{
    public function safeUp()
    {
        $this->alterColumn('payment_methods', 'outCommissionPercent', $this->decimal(19,2));

        $orders = (new Query())
            ->from('payment_methods')
            ->all();

        foreach ($orders as $order){
            if($order['outCommissionPercent'] === 0 || !$order['outCommissionPercent']){
                continue;
            }
            $this->update('payment_methods', ['outCommissionPercent' => $order['outCommissionPercent'] / 100], ['id' => $order['id']]);
        }
    }

    public function safeDown()
    {
        $orders = (new Query())
            ->from('payment_methods')
            ->all();

        foreach ($orders as $order){
            if($order['outCommissionPercent'] === 0 || !$order['outCommissionPercent']){
                continue;
            }
            $this->update('payment_methods', ['outCommissionPercent' => $order['outCommissionPercent'] * 100], ['id' => $order['id']]);
        }

        $this->alterColumn('payment_methods', 'outCommissionPercent', $this->integer());
    }
}

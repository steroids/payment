<?php

namespace steroids\payment\commands;

use steroids\core\structure\RequestInfo;
use steroids\payment\enums\PaymentStatus;
use steroids\payment\models\PaymentOrder;
use steroids\payment\structure\PaymentProcess;
use yii\console\Controller;
use yii\console\Exception;
use yii\console\widgets\Table;
use yii\helpers\Url;

class PaymentCommand extends Controller
{
    /**
     * @param string $id Order id
     * @param string $action One of command: success, failure
     */
    public function actionOrders($id = null, $action = null)
    {
        if (!$id && !$action) {
            $attributes = [
                'id',
                'description',
                'methodId',
                'payerUserId',
                'externalId',
                'inAmount',
                'inCurrencyCode',
                'status',
                'errorMessage',
            ];
            echo Table::widget([
                'headers' => $attributes,
                'rows' => array_reverse(
                    PaymentOrder::find()
                        ->select($attributes)
                        ->orderBy(['id' => SORT_DESC])
                        ->limit(20)
                        ->asArray()
                        ->all()
                ),
            ]);

            echo "\nAvailable commands:\n";
            echo "\t- php yii payment/orders 999 success\n";
            echo "\t- php yii payment/orders 999 failure\n";
        } else {
            $order = PaymentOrder::findOrPanic(['id' => $id]);
            $request = new RequestInfo([
                'url' => '',
                'params' => [
                    'orderId' => $order->getId(),
                ]
            ]);
            $process = new PaymentProcess();
            switch ($action) {
                case 'success':
                    $process->newStatus = PaymentStatus::SUCCESS;
                    $order->end($request, $process);
                    break;

                case 'failure':
                    $process->newStatus = PaymentStatus::FAILURE;
                    $order->end($request, $process);
                    break;

                default:
                    throw new Exception('Wrong action: ' . $action);
            }
        }
    }
}

<?php

namespace steroids\payment\commands;

use steroids\core\structure\RequestInfo;
use steroids\payment\enums\PaymentStatus;
use steroids\payment\models\PaymentOrder;
use steroids\payment\models\PaymentProviderLog;
use steroids\payment\structure\PaymentProcess;
use yii\console\Controller;
use yii\console\Exception;
use yii\console\widgets\Table;
use yii\helpers\StringHelper;
use yii\helpers\Url;

class PaymentCommand extends Controller
{
    /**
     * @param string $id Order id
     * @param string $action One of command: success, failure
     */
    public function actionOrders($id = null, $action = null)
    {
        if (!$action) {
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
                        ->andFilterWhere(['id' => $id ? StringHelper::explode($id) : null])
                        ->asArray()
                        ->all()
                ),
            ]);

            echo "\nAvailable commands:\n";
            echo "\t- php yii payment/orders 999 callback\n";
            echo "\t- php yii payment/orders 999 success\n";
            echo "\t- php yii payment/orders 999 failure\n";
            echo "\t- php yii payment/orders 999 redo\n";
        } else {
            $order = PaymentOrder::findOrPanic(['id' => $id]);
            $request = new RequestInfo([
                'url' => 'http://test',
                'params' => [
                    'orderId' => $order->getId(),
                ]
            ]);
            switch ($action) {
                case 'redo':
                    $action = $order->status;
                    $order->status = PaymentStatus::PROCESS;
                    $order->saveOrPanic();
                    $this->actionOrders($id, $action);
                    break;

                case 'success':
                    $process = new PaymentProcess();
                    $process->newStatus = PaymentStatus::SUCCESS;
                    $order->end($request, $process);
                    break;

                case 'failure':
                    $process = new PaymentProcess();
                    $process->newStatus = PaymentStatus::FAILURE;
                    $order->end($request, $process);
                    break;

                case 'callback':
                    if (!$order->lastProviderLogger || $order->lastProviderLogger->callMethod !== 'callback') {
                        echo "No callback logs\n";
                        return;
                    }

                    $order->callback(RequestInfo::createFromRaw($order->lastProviderLogger->requestRaw));
                    break;

                default:
                    throw new Exception('Wrong action: ' . $action);
            }
        }
    }
}

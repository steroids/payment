<?php

namespace steroids\payment\controllers;

use steroids\core\structure\RequestInfo;
use steroids\payment\enums\PaymentStatus;
use steroids\payment\forms\PaymentOrdersSearch;
use steroids\payment\models\PaymentOrder;
use steroids\payment\structure\PaymentProcess;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;

class PaymentAdminController extends Controller
{
    public static function apiMap($baseUrl = '/api/v1/admin/payment')
    {
        return [
            'admin.payment' => [
                'items' => [
                    'get-orders' => "GET $baseUrl/orders",
                    'order-accept' => "POST $baseUrl/orders/<orderId>/accept",
                    'order-reject' => "POST $baseUrl/orders/<orderId>/reject",
                ],
            ],
        ];
    }

    /**
     * @return PaymentOrdersSearch
     */
    public function actionGetOrders()
    {
        $model = new PaymentOrdersSearch();
        $model->search(\Yii::$app->request->get());
        return $model;
    }

    /**
     * @param $orderId
     * @return PaymentOrder
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionOrderAccept($orderId)
    {
        return $this->actionOrderStatus($orderId, PaymentStatus::SUCCESS);
    }

    /**
     * @param $orderId
     * @return PaymentOrder
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionOrderReject($orderId)
    {
        return $this->actionOrderStatus($orderId, PaymentStatus::FAILURE);
    }

    /**
     * @param $orderId
     * @param $newStatus
     * @return PaymentOrder
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws \yii\web\NotFoundHttpException
     */
    protected function actionOrderStatus($orderId, $newStatus)
    {
        $order = PaymentOrder::findOrPanic(['id' => (int)$orderId]);
        if (!$order->canUpdate(\Yii::$app->user->model)) {
            throw new ForbiddenHttpException();
        }

        if ($order->status !== PaymentStatus::PROCESS) {
            throw new BadRequestHttpException();
        }

        $process = new PaymentProcess();
        $process->newStatus = $newStatus;
        $order->end(RequestInfo::createFromYii(), $process);

        return $order;
    }
}

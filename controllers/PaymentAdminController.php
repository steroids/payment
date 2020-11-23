<?php

namespace steroids\payment\controllers;

use steroids\payment\enums\PaymentStatus;
use steroids\payment\forms\PaymentOrdersSearch;
use steroids\payment\models\PaymentOrder;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

class PaymentAdminController extends PaymentOrdersController
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
        return PaymentOrdersController::switchOrderStatus($orderId, PaymentStatus::SUCCESS);
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
        return PaymentOrdersController::switchOrderStatus($orderId, PaymentStatus::FAILURE);
    }
}

<?php

namespace steroids\payment\controllers;

use Yii;
use steroids\core\base\CrudApiController;
use steroids\payment\enums\PaymentDirection;
use steroids\payment\enums\PaymentStatus;
use steroids\payment\forms\PaymentOrdersSearch;
use steroids\payment\models\PaymentMethod;
use steroids\payment\models\PaymentOrder;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

class PaymentAdminController extends CrudApiController
{
    public static $modelClass = PaymentOrder::class;
    public static $searchModelClass = PaymentOrdersSearch::class;

    public static function apiMap($baseUrl = '/api/v1/admin/payment')
    {
        return [
            'admin.payment' => static::apiMapCrud('/api/v1/admin/payment', [
                'items' => [
                    //@todo change to get after dataProvider in frontend can send get request
                    'withdraw-methods' => "GET,POST $baseUrl/withdraw-methods",
                    'get-orders' => "GET $baseUrl/orders",
                    'order-accept' => "POST $baseUrl/orders/<orderId>/accept",
                    'order-reject' => "POST $baseUrl/orders/<orderId>/reject",
                ],
            ]),
        ];
    }

    public function actionWithdrawMethods()
    {
        return array_map(function ($method) {
            return $method->toFrontend([
                'id',
                'label' => fn(PaymentMethod $method) => $method->title
            ]);
        }, PaymentMethod::findAll(['direction' => PaymentDirection::WITHDRAW]));
    }

    public function actionUpdate()
    {
        $model = $this->findModel();
        $paymentMethod = PaymentMethod::findOrPanic(['id' => Yii::$app->request->post('methodId')]);
        return $this->actionSave($model, [
            'methodId' =>  $paymentMethod->id,
            'outCurrencyCode' => $paymentMethod->outCurrencyCode,
            'outCommissionFixed' => $paymentMethod->outCommissionFixed,
            'outCommissionPercent' => $paymentMethod->outCommissionPercent,
        ]);
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

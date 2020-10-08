<?php

namespace steroids\payment\controllers;

use steroids\core\structure\RequestInfo;
use steroids\payment\enums\PaymentDirection;
use steroids\payment\enums\PaymentStatus;
use steroids\payment\forms\PaymentStartForm;
use steroids\payment\models\PaymentMethod;
use steroids\payment\models\PaymentOrder;
use steroids\payment\PaymentModule;
use steroids\payment\providers\BaseProvider;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\web\Controller;
use yii\web\Response;

class PaymentController extends Controller
{
    /**
     * @inheritDoc
     */
    public $enableCsrfValidation = false;

    public static function apiMap()
    {
        return [
            'payment' => [
                'items' => [
                    'start' => 'api/v1/payment/charge',
                ],
            ],
        ];
    }

    public static function siteMap()
    {
        return [
            'payment' => [
                'items' => [
                    'test' => 'backend/payment/test',
                    'callback' => 'backend/payment/<methodName>/callback',
                    'success' => 'backend/payment/<methodName>/success',
                    'failure' => 'backend/payment/<methodName>/failure',
                ],
            ],
        ];
    }

    /**
     * @return PaymentStartForm
     */
    public function actionCharge()
    {
        $model = new PaymentStartForm();
        $model->direction = PaymentDirection::CHARGE;
        $model->load(\Yii::$app->request->post());
        return $model;
    }

    /**
     * @param string $methodName
     * @return mixed
     * @throws InvalidConfigException
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionCallback(string $methodName)
    {
        $order = $this->findOrder($this->findProvider($methodName));
        if (!$order) {
            throw new InvalidConfigException("Cannot resolve order id for method '$methodName'");
        }

        // Get request
        $request = RequestInfo::createFromYii();

        // Run callback
        $process = $order->callback($request);

        // Return raw response
        \Yii::$app->response->format = Response::FORMAT_RAW;
        return $process->responseText;
    }

    /**
     * @param string $methodName
     * @return Response
     * @throws InvalidConfigException
     * @throws \steroids\core\exceptions\ModelSaveException
     * @throws \steroids\payment\exceptions\PaymentException
     * @throws \yii\base\Exception
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionSuccess(string $methodName)
    {
        return $this->actionFinish($methodName, PaymentStatus::SUCCESS);
    }

    /**
     * @param string $methodName
     * @return Response
     * @throws InvalidConfigException
     * @throws \steroids\core\exceptions\ModelSaveException
     * @throws \steroids\payment\exceptions\PaymentException
     * @throws \yii\base\Exception
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionFailure(string $methodName)
    {
        return $this->actionFinish($methodName, PaymentStatus::FAILURE);
    }

    /**
     * @param string $methodName
     * @param string $status
     * @return Response
     * @throws InvalidConfigException
     * @throws \steroids\core\exceptions\ModelSaveException
     * @throws \steroids\payment\exceptions\PaymentException
     * @throws \yii\base\Exception
     * @throws \yii\web\NotFoundHttpException
     */
    protected function actionFinish(string $methodName, string $status)
    {
        // Get request
        $request = RequestInfo::createFromYii();

        // Get provider
        $provider = $this->findProvider($methodName);

        // Get redirect url
        $order = $this->findOrder($provider, $request);
        $redirectUrl = $order && $order->redirectUrl
            ? $order->redirectUrl
            : PaymentModule::getInstance()->siteUrl;

        // Get error
        $error = $status === PaymentStatus::FAILURE ? $provider->resolveErrorMessage($request) : null;
        if ($error && !$order->errorMessage) {
            // Save to order
            $order->errorMessage = $error;
            $order->saveOrPanic();
        }

        // Add status and error
        $redirectUrl .= (strpos($redirectUrl, '?') === false ? '?' : '&');
        $redirectUrl .= http_build_query(array_filter([
            'status' => $status,
            'error' => $status === PaymentStatus::FAILURE
                ? $provider->resolveErrorMessage($request)
                : null,
        ]));

        return $this->redirect($redirectUrl);

    }

    /**
     * @param string $methodName
     * @return BaseProvider
     * @throws InvalidConfigException
     * @throws \steroids\payment\exceptions\PaymentException
     * @throws \yii\base\Exception
     */
    protected function findProvider(string $methodName)
    {
        $method = PaymentMethod::getByName($methodName);

        // Get provider
        /** @var BaseProvider $provider */
        $provider = PaymentModule::getInstance()->getProvider($method->providerName);
        if (!$provider) {
            throw new InvalidConfigException("Not found payment provider '{$method->providerName}'");
        }

        return $provider;
    }

    /**
     * @param BaseProvider $provider
     * @param RequestInfo|null $request
     * @return PaymentOrder|null
     * @throws \yii\web\NotFoundHttpException
     */
    protected function findOrder(BaseProvider $provider, RequestInfo $request = null)
    {
        // Get request
        if (!$request) {
            $request = RequestInfo::createFromYii();
        }

        // Get order id
        $orderId = $provider->resolveOrderId($request);

        // Get order and run callback
        return $orderId ? PaymentOrder::findOrPanic(['id' => $orderId]) : null;
    }

    /*protected function redirectPost(string $url, $params = [])
    {
        $html = '';
        $html .= Html::beginForm($url, 'post', ['name' => 'redirectForm']);
        foreach ($params as $key => $value) {
            $html .= Html::hiddenInput($key, $value);
        }
        $html .= Html::endForm();
        $html .= Html::script('document.redirectForm.submit()');

        return $html;
    }*/
}

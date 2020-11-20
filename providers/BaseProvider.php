<?php

namespace steroids\payment\providers;

use steroids\core\structure\RequestInfo;
use steroids\payment\models\PaymentOrderInterface;
use steroids\payment\PaymentModule;
use steroids\payment\structure\PaymentProcess;
use yii\base\BaseObject;

/**
 * Class BaseProvider
 * @package steroids\payment\providers
 * @property-read PaymentModule $module
 */
abstract class BaseProvider extends BaseObject
{

    /**
     * Имя платёжного шлюза, одно из значений enum GatewayName
     * @var string
     */
    public string $name;

    /**
     * Флаг, отображающий включена ли платёжный шлюз.
     * @var boolean
     */
    public bool $enable = true;

    /**
     * Флаг, отображающий включен ли платёжный шлюз для реальных транзакций.
     * По-умолчанию включен режим разработчика.
     * @var boolean
     */
    public bool $testMode = false;

    /**
     * @param PaymentOrderInterface $order
     * @param RequestInfo $request
     * @return PaymentProcess
     */
    abstract public function start(PaymentOrderInterface $order, RequestInfo $request);

    /**
     * @param PaymentOrderInterface $order
     * @param RequestInfo $request
     * @return PaymentProcess
     */
    abstract public function callback(PaymentOrderInterface $order, RequestInfo $request);

    /**
     * @param RequestInfo $request
     * @return int
     */
    abstract public function resolveOrderId(RequestInfo $request);

    /**
     * Метод нахождения текста ошибки в ссылке, на которую платежная система перенаправляет при неуспешной оплате.
     * Провайдер должен знать в каком параметре передается ошибка и отформатировать ее для показа пользователю
     * @param RequestInfo $request
     * @return int
     */
    abstract public function resolveErrorMessage(RequestInfo $request);

    /**
     * @return \steroids\core\base\Module|PaymentModule
     * @throws \yii\base\Exception
     */
    public function getModule()
    {
        return PaymentModule::getInstance();
    }



    /*protected static function appendToUrl($url, $query)
    {
        return $url . (strpos($url, '?') === false ? '?' : '&') . $query;
    }

    protected function log($message, $level = Logger::LEVEL_INFO, $transactionId = null, $stateData = array())
    {
        $this->module->log($message, $level, $transactionId, $stateData);
    }

    protected function httpSend($url, $params = [], $headers = [])
    {
        return $this->module->httpSend($url, $params, $headers);
    }*/
}

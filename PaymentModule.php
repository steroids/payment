<?php

namespace steroids\payment;

use steroids\core\base\Module;
use steroids\core\traits\ModuleProvidersTrait;
use steroids\payment\models\PaymentMethod;
use steroids\payment\models\PaymentMethodParam;
use steroids\payment\models\PaymentOrder;
use steroids\payment\models\PaymentOrderItem;
use steroids\payment\models\PaymentProviderLog;
use steroids\payment\providers\CloudpaymentsProvider;
use steroids\payment\providers\TeleportProvider;
use steroids\payment\providers\TestProvider;
use steroids\payment\providers\PayInPayOutProvider;
use steroids\payment\providers\PayPalProvider;
use steroids\payment\providers\RobokassaProvider;
use steroids\payment\providers\TinkoffProvider;
use steroids\payment\providers\YandexKassaProvider;
use yii\helpers\Json;
use yii\helpers\Url;

/**
 * Class PaymentModule
 * @package steroids\payment
 * @property-read string $siteUrl
 * @property-read string $successUrl
 * @property-read string $failureUrl
 */
class PaymentModule extends Module
{
    use ModuleProvidersTrait;

    const PROVIDER_TEST = 'test';

    /**
     * @event ProcessEvent
     */
    const EVENT_START = 'start';

    /**
     * @event ProcessEvent
     */
    const EVENT_CALLBACK = 'callback';

    /**
     * @event ProcessEvent
     */
    const EVENT_END = 'end';

    /**
     * @var array
     */
    public array $providersClasses = [];

    public function init()
    {
        parent::init();

        $this->classesMap = array_merge([
            '\steroids\payment\models\PaymentMethod' => PaymentMethod::class,
            '\steroids\payment\models\PaymentMethodParam' => PaymentMethodParam::class,
            '\steroids\payment\models\PaymentOrder' => PaymentOrder::class,
            '\steroids\payment\models\PaymentOrderItem' => PaymentOrderItem::class,
            '\steroids\payment\models\PaymentProviderLog' => PaymentProviderLog::class,
        ], $this->classesMap);

        $this->providersClasses = array_merge([
            'test' => TestProvider::class,
            'payInPayOut' => PayInPayOutProvider::class,
            'payPal' => PayPalProvider::class,
            'robokassa' => RobokassaProvider::class,
            'yandexKassa' => YandexKassaProvider::class,
            'tinkoff' => TinkoffProvider::class,
            'cloudpayments' => CloudpaymentsProvider::class,
            'teleport' => TeleportProvider::class,
        ], $this->providersClasses);
    }

    public function getSiteUrl()
    {
        return Url::to(\Yii::$app->homeUrl, true);
    }

    public function getCallbackUrl($methodName, $params = [])
    {
        return Url::to(array_merge(['/payment/payment/callback', 'methodName' => $methodName], $params), true);
    }

    public function getSuccessUrl($methodName, $params = [])
    {
        return Url::to(array_merge(['/payment/payment/success', 'methodName' => $methodName], $params), true);
    }

    public function getFailureUrl($methodName, $params = [])
    {
        return Url::to(array_merge(['/payment/payment/failure', 'methodName' => $methodName], $params), true);
    }
}

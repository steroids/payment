<?php

namespace steroids\payment\providers;

use steroids\core\structure\RequestInfo;
use steroids\payment\enums\PaymentStatus;
use steroids\payment\exceptions\PaymentProcessException;
use steroids\payment\exceptions\SignatureMismatchRequestException;
use steroids\payment\models\PaymentOrderInterface;
use steroids\payment\structure\PaymentProcess;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

class TinkoffProvider extends BaseProvider
{

    /**
     *    Идентификатор терминала. Выдается продавцу банком при заведении терминала
     * @var string
     */
    public $terminalKey;

    /**
     * Пароль
     * @var string
     */
    public $password;

    /**
     * See API: https://oplata.tinkoff.ru/develop/api/payments/init-description/
     * @inheritDoc
     */
    public function start(PaymentOrderInterface $order, RequestInfo $request)
    {
        $params = [
            'TerminalKey' => $this->terminalKey,
            'Amount' => $order->getOutAmount(),
            'OrderId' => $order->getId(),
            'IP' => \Yii::$app->request->getUserIP(),
            'Description' => $order->getDescription(),
            'NotificationURL' => $this->module->getCallbackUrl($order->getMethodName()),
            'SuccessURL' => $this->module->getSuccessUrl($order->getMethodName()),
            'FailURL' => $this->module->getFailureUrl($order->getMethodName()),
        ];
        $response = $this->httpSend('https://securepay.tinkoff.ru/v2/Init', array_merge(
            $params,
            ['token' => $this->generateToken($params)]
        ));

        return new PaymentProcess([
            'request' => new RequestInfo([
                'url' => ArrayHelper::getValue($response, 'PaymentURL'),
            ]),
        ]);
    }

    /**
     * See API: https://oplata.tinkoff.ru/develop/api/notifications/
     * @inheritDoc
     */
    public function callback(PaymentOrderInterface $order, RequestInfo $request)
    {
        $this->validateToken($request->params);
        $order->setExternalId($request->getParam('PaymentId'));

        if ($request->getParam('ErrorCode') === '1051') {
            $order->setErrorMessage(\Yii::t('steroids', 'Недостаточно средств'));
        }

        $newStatusMap = [
            'AUTHORIZED' => null,
            'CONFIRMED' => PaymentStatus::SUCCESS,
        ];

        return new PaymentProcess([
            'newStatus' => ArrayHelper::getValue($newStatusMap, $request->getParam('Status'), PaymentStatus::FAILURE),
            'responseText' => 'OK',
        ]);
    }

    /**
     * @inheritDoc
     */
    public function resolveOrderId(RequestInfo $request)
    {
        return ArrayHelper::getValue($request->params, 'OrderId');
    }

    /**
     * @inheritDoc
     */
    public function resolveErrorMessage(RequestInfo $request)
    {
        return ArrayHelper::getValue($request->params, 'ErrorMessage')
            ?: ArrayHelper::getValue($request->params, 'ErrorCode');
    }

    /**
     * See documentation: https://oplata.tinkoff.ru/develop/api/request-sign/
     * @param array $params
     * @return string
     */
    protected function generateToken(array $params)
    {
        ArrayHelper::remove($params, 'methodName');
        ArrayHelper::remove($params, 'DATA');
        ArrayHelper::remove($params, 'Receipt');
        ArrayHelper::remove($params, 'Items');
        ArrayHelper::remove($params, 'Token');

        $params['Password'] = $this->password;
        $params['TerminalKey'] = $this->terminalKey;
        ksort($params);

        // Normalize values
        $values = [];
        foreach ($params as $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $values[] = $value;
        }

        return hash('sha256', implode('', $values));
    }

    /**
     * See documentation: https://oplata.tinkoff.ru/develop/api/notifications/setup-request-sign/
     * @param array $params
     * @throws PaymentProcessException
     * @throws SignatureMismatchRequestException
     */
    protected function validateToken(array $params)
    {
        $remoteToken = ArrayHelper::getValue($params, 'Token');
        if (!$remoteToken) {
            throw new PaymentProcessException('Not found params Token');
        }

        $token = $this->generateToken($params);
        if (strcmp(strtolower($remoteToken), strtolower($token)) !== 0) {
            throw new SignatureMismatchRequestException('Invalidate token');
        }
    }

    /**
     * @param string $url
     * @param array $params
     * @return array
     */
    protected function httpSend(string $url, array $params = [])
    {
        $data = file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\n", [
                    'Content-Type: application/json',
                ]),
                'content' => Json::encode($params),
            ],
        ]));

        return $data ? Json::decode($data) : null;
    }
}

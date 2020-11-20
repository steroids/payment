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
use yii\helpers\Url;

/**
 * Class CloudpaymentsProvider
 * Test cards:
 *   4242 4242 4242 4242
 * @package steroids\payment\providers
 */
class CloudpaymentsProvider extends BaseProvider
{

    /**
     *    Идентификатор терминала. Выдается продавцу банком при заведении терминала
     * @var string
     */
    public $publicId;

    /**
     * Пароль
     * @var string
     */
    public $apiSecret;

    /**
     * @inheritDoc
     */
    public function start(PaymentOrderInterface $order, RequestInfo $request)
    {
        return new PaymentProcess([
            'request' => new RequestInfo([
                'url' => Url::to(['/payment/payment/cloudpayments'], true),
                'params' => [
                    'orderId' => $order->getId(),
                ]
            ]),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function callback(PaymentOrderInterface $order, RequestInfo $request)
    {
        if ((int)$request->getParam('AccountId') !== $order->getPayerUserId()) {
            return new PaymentProcess([
                'newStatus' => PaymentStatus::FAILURE,
                'responseText' => Json::encode(['code' => 11]), // Некорректный AccountId	Платеж будет отклонен
            ]);
        }

        if ((float)$request->getParam('Amount') * 100 !== $order->getOutAmount()) {
            return new PaymentProcess([
                'newStatus' => PaymentStatus::FAILURE,
                'responseText' => Json::encode(['code' => 12]), // Неверная сумма	Платеж будет отклонен
            ]);
        }

        // Validate request (HMAC)
        $this->validateToken($request);

        // Set external id
        $order->setExternalId($request->getParam('TransactionId'));

        $newStatusMap = [
            'Authorized' => null,
            'Completed' => PaymentStatus::SUCCESS,
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
        return ArrayHelper::getValue($request->params, 'InvoiceId');
    }

    /**
     * @inheritDoc
     */
    public function resolveErrorMessage(RequestInfo $request)
    {
        return ArrayHelper::getValue($request->params, 'Reason');
    }

    /**
     * See documentation: https://oplata.tinkoff.ru/develop/api/request-sign/
     * @param array $params
     * @return string
     */
    protected function generateToken(array $params)
    {
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
     * @param RequestInfo $request
     * @throws PaymentProcessException
     * @throws SignatureMismatchRequestException
     */
    protected function validateToken(RequestInfo $request)
    {
        $remoteHmac = ArrayHelper::getValue($request->headers, 'X-Content-HMAC');
        if (!$remoteHmac) {
            throw new PaymentProcessException('Not found header X-Content-HMAC');
        }

        // Generate hash
        $body = file_get_contents('php://input');
        $hmac = base64_encode(hash_hmac('sha256', $body, $this->apiSecret, true));

        // Check
        if (strcmp($remoteHmac, $hmac) !== 0) {
            throw new SignatureMismatchRequestException($request->params);
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

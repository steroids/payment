<?php

namespace steroids\payment\providers;

use Yii;
use steroids\core\structure\RequestInfo;
use steroids\payment\enums\PaymentStatus;
use steroids\payment\exceptions\PaymentProcessException;
use steroids\payment\models\PaymentOrderInterface;
use steroids\payment\structure\PaymentProcess;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

/**
 * Class AlfabankProvider
 *
 * Номер карты: 4111 1111 1111 1111
 * Срок действия: 12/24
 * CVC2: 111
 * Код подтверждения: 12345678
 * Имя держателя карты:
 *   - Успешно: SUCCESS PAYMENT
 *   - Ошибка: ERROR PAYMENT
 *
 * Другие карты - https://pay.alfabank.ru/ecommerce/instructions/merchantManual/pages/index/test_cards.html
 *
 * @see https://pay.alfabank.ru/ecommerce/instructions/merchantManual/pages/index/plugins.html
 * @package steroids\payment\providers
 */
class AlfabankProvider extends BaseProvider
{
    /**
     * Логин магазина, полученный при подключении
     * @var string
     */
    public $username;

    /**
     * Пароль магазина, полученный при подключении
     * @var string
     */
    public $password;

    /**
     * Код валюты платежа ISO 4217. Если не указан, считается равным 810 (российские рубли).
     * @var
     */
    public $currency = 810;

    /**
     * Язык в кодировке ISO 639-1. Если не указан, будет использован язык, указанный в настройках магазина как язык по умолчанию.
     * @var string
     */
    public $language = 'ru';

    /**
     * @inheritDoc
     */
    public function start(PaymentOrderInterface $order, RequestInfo $request)
    {
        // https://web.rbsuat.com/ab/swagger/swagger.html#/reverse/reverseOrder_15
        $response = $this->httpSend('https://server/payment/rest/register.do', [
            'userName' => $this->username,
            'password' => $this->password,
            'orderNumber' => $order->getId(),
            'amount' => $order->getOutAmount(),
            'currency' => 810,
            'returnUrl' => $this->module->getSuccessUrl($order->getMethodName()),
            'failUrl' => $this->module->getFailureUrl($order->getMethodName()),
            'description' => mb_substr($order->getDescription(), 0, 512)
        ]);

        if (empty($response['formUrl'])) {
            throw new PaymentProcessException('Not found payment url. Wrong response: ' . print_r($response, true));
        }

        return new PaymentProcess([
            'request' => new RequestInfo([
                'method' => RequestInfo::METHOD_GET,
                'url' => $response['formUrl'],
            ]),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function callback(PaymentOrderInterface $order, RequestInfo $request)
    {
        $response = $this->httpSend('https://server/payment/rest/getOrderStatus.do', [
            'userName' => $this->username,
            'password' => $this->password,
            'orderId' => $order->getId(),
        ]);

        /*
         *  Статус  Описание
         *  0       Заказ зарегистрирован, но не оплачен.
         *  1       Предавторизованная сумма захолдирована (для двухстадийных платежей).
         *  2       Проведена полная авторизация суммы заказа.
         *  3       Авторизация отменена.
         *  4       По транзакции была проведена операция возврата.
         *  5       Инициирована авторизация через ACS банка-эмитента.
         *  6       Авторизация отклонена.
         */
        $newStatus = null;
        if ($response['ErrorCode'] === 0 && $response['OrderStatus'] === 2) {
            $newStatus = PaymentStatus::SUCCESS;
        } elseif (in_array($response['OrderStatus'], [3, 4, 6])) {
            $newStatus = PaymentStatus::FAILURE;
        }

        // Set error
        $errorsMap = [
            2 => Yii::t('steroids', 'Заказ отклонен по причине ошибки в реквизитах платежа'),
            5 => Yii::t('steroids', 'Доступ запрещён'),
            6 => Yii::t('steroids', 'Неизвестный номер заказа'),
            7 => Yii::t('steroids', 'Системная ошибка'),
        ];
        if (isset($response['ErrorCode'])) {
            $order->setErrorMessage(
                ArrayHelper::getValue($response, 'ErrorMessage')
                    ?: ArrayHelper::getValue($errorsMap, $response['ErrorCode'])
                    ?: '#' . $response['ErrorCode']
            );
        }

        if (!empty($response)) {
            return new PaymentProcess([
                'newStatus' => $newStatus,
                'responseText' => 'OK',
            ]);
        }
    }

    /**
     * @inheritDoc
     */
    public function resolveOrderId(RequestInfo $request)
    {
        return ArrayHelper::getValue($request->params, 'orderId');
    }

    /**
     * @inheritDoc
     */
    public function resolveErrorMessage(RequestInfo $request)
    {
        return ArrayHelper::getValue($request->params, 'errorMessage')
            ?: ArrayHelper::getValue($request->params, 'ErrorMessage')
                ?: ArrayHelper::getValue($request->params, 'errorCode')
                    ?: ArrayHelper::getValue($request->params, 'ErrorCode');
    }

    /**
     * @param string $url
     * @param array $params
     * @return array
     */
    protected function httpSend(string $url, array $params = [])
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
    }
}

<?php

namespace steroids\payment\structure;

use steroids\core\structure\RequestInfo;
use yii\base\BaseObject;

class PaymentProcess extends BaseObject
{
    /**
     * Запрос, который необходимо послать платёжной системе. Как правило, возвращается при начале
     * операции (состояние WAIT_VERIFICATION), для получения формы инициализации платежа, чтобы передать ее в браузер.
     * @var RequestInfo
     */
    public ?RequestInfo $request = null;

    /**
     * Результат изменения состояния
     * @var string
     */
    public ?string $newStatus = null;

    /**
     * Строка (обычно это текст, xml или json), которую необходимо передать платёжной системе. Как правило, возвращается
     * как ответ при проверке статуса или завершения транзакции.
     * @var string
     */
    public ?string $responseText = null;

}

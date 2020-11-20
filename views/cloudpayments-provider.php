<?php

namespace steroids\views;

use steroids\payment\models\PaymentOrder;
use steroids\payment\PaymentModule;
use steroids\payment\providers\CloudpaymentsProvider;
use yii\helpers\Json;

/** @var PaymentOrder $order */
/** @var CloudpaymentsProvider $provider */

?>

<script src="https://widget.cloudpayments.ru/bundles/cloudpayments"></script>
<script>
    (new cp.CloudPayments()).pay(
        'charge',
        <?= Json::encode([
            'publicId' => $provider->publicId,
            'description' => $order->getDescription(),
            'amount' => round($order->getOutAmount() / 100, 2),
            'currency' => 'RUB',
            'invoiceId' => $order->getId(),
            'accountId' => $order->payerUserId,
            'skin' => 'modern',
        ])?>,
        {
            onSuccess: function () {
                location.href = '<?= PaymentModule::getInstance()->getSuccessUrl($order->getMethodName()) ?>';
            },
            onFail: function (reason) {
                location.href = '<?= PaymentModule::getInstance()->getFailureUrl($order->getMethodName(), ['InvoiceId' => $order->getId(), 'Reason' => '{REASON}']) ?>'
                    .replace('{REASON}', reason);
            }
        }
    );
</script>
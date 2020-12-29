<?php

namespace steroids\views;

use \Yii;
use steroids\payment\models\PaymentOrder;

/** @var PaymentOrder $order */

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"
          integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
</head>
<body>

<div class="d-flex flex-column flex-md-row align-items-center p-3 px-md-4 mb-3 bg-white border-bottom shadow-sm">
    <h5 class="my-0 mr-md-auto font-weight-normal"><?= Yii::$app->name ?></h5>
</div>

<div class="pricing-header px-3 py-3 pt-md-5 pb-md-4 mx-auto text-center">
    <h1 class="display-5 font-weight-normal"><?= \Yii::t('app', 'Оплата ордера №{id}', ['id' => $order->id]) ?></h1>
    <p>
        <?= \Yii::t('app', 'Свяжитесь с оператором сайта для ручного перевода средств в оффлайн режиме') ?>
    </p>
</div>

</body>
</html>
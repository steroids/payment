<?php

namespace steroids\views;

use \Yii;
use steroids\payment\enums\PaymentStatus;
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
    <h1 class="display-5 font-weight-normal">Тестовая оплата</h1>
    <p>
        Данная страница предназначена для тестирования платежного шлюза и оплат через него.
    </p>
</div>

<div class="container" style="max-width: 400px">
    <div class="card-deck mb-3 text-center">
        <div class="card mb-4 shadow-sm">
            <div class="card-header">
                <h4 class="my-0 font-weight-normal">
                    Ордер #<?= $order->primaryKey ?>
                </h4>
            </div>
            <div class="card-body">
                <p class="mb-1">
                    <?= $order->description ?: 'Пополнение счета' ?>
                </p>
                <p class="mb-1">
                    Сумма: <?= $order->outCurrency->format($order->outAmount) ?>
                </p>
                <p class="mb-1">
                    Статус: <?= PaymentStatus::getLabel($order->status) ?>
                </p>
                <?php if ($order->errorMessage) { ?>
                    <p class="mb-1 text-danger">
                        <?= $order->errorMessage ?>
                    </p>
                <?php } ?>
                <?php if (!in_array($order->status, PaymentStatus::getFinishStatuses())) { ?>
                    <form method="post" class="mt-3">
                        <div class="row">
                            <div class="col-6">
                                <button type="submit" name="<?= PaymentStatus::SUCCESS ?>" class="btn btn-lg btn-block btn-outline-success">
                                    Успешно
                                </button>
                            </div>
                            <div class="col-6">
                                <button type="submit" name="<?= PaymentStatus::FAILURE ?>" class="btn btn-lg btn-block btn-outline-danger">
                                    Ошибка
                                </button>
                            </div>
                        </div>
                    </form>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
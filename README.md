# Payment

Модуль оплаты через платежные системы

### Пополнение счета в биллинге

```php
$account = \steroids\billing\models\BillingAccount::findOrCreate('main', 'usd', $userId);
$method = \steroids\payment\models\PaymentMethod::getByName('mymethod');
$order = $method->createOrder($userId, $account->currency->code, 10000, [
    'description' => 'Пополнение счета',
]);
$order->addOperation(new PaymentChargeOperation([
    'fromAccount' => $method->systemAccount,
    'toAccount' => $account,
    'amount' => 10000,
    'document' => $order,
]));

$process = $order->start(\steroids\core\structure\RequestInfo::createFromYii());

// URL для переадресации пользователя
$url = (string)$process->request;
```

### Оплата товара (без биллинга)

```php
$order = \steroids\payment\models\PaymentMethod::getByName('mymethod')
    ->createOrder($userId, 'usd', 5000, [
        'description' => 'Оплата ЛК на месяц (50$)',
    ])
    ->addOperation(new AccountPaymentOperation([
        // Документ будет создан в БД, когда платеж будет выполнен
        'document' => [
            'period' => 'month',
            'userId' => $userId,
        ],
]));

$process = $order->start(\steroids\core\structure\RequestInfo::createFromYii());

// URL для переадресации пользователя
$url = (string)$process->request;
```
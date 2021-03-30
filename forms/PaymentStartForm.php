<?php

namespace steroids\payment\forms;

use steroids\auth\UserInterface;
use steroids\billing\models\BillingAccount;
use steroids\billing\models\BillingCurrency;
use steroids\core\structure\RequestInfo;
use steroids\payment\enums\PaymentDirection;
use steroids\payment\enums\PaymentMethodEnum;
use steroids\payment\exceptions\PaymentException;
use steroids\payment\forms\meta\PaymentStartFormMeta;
use steroids\payment\models\PaymentMethod;
use steroids\payment\models\PaymentOrder;
use steroids\payment\operations\PaymentChargeOperation;
use steroids\payment\operations\PaymentWithdrawReserveOperation;
use steroids\payment\operations\PaymentWithdrawRollbackOperation;
use steroids\payment\structure\PaymentProcess;
use yii\helpers\ArrayHelper;
use yii\validators\RequiredValidator;
use yii\web\IdentityInterface;

/**
 * Class PaymentStartForm
 * @package steroids\payment\forms
 * @property-read BillingAccount $account
 * @property-read PaymentMethod $method
 */
class PaymentStartForm extends PaymentStartFormMeta
{
    /**
     * @var array
     */
    public array $custom = [];

    public string $direction;

    /**
     * @var UserInterface|IdentityInterface
     */
    public $user;

    public ?RequestInfo $request = null;

    public ?PaymentOrder $order = null;

    public ?PaymentProcess $process = null;

    public ?string $description = null;

    public ?string $redirectUrl = null;

    private ?BillingAccount $_account = null;

    public function fields()
    {
        return [
            'order' => [
                'id',
                'status',
            ],
            'url' => fn() => (string)$this->process->request,
        ];
    }

    /**
     * @inheritDoc
     */
    public function rules()
    {
        // TODO validate custom attributes

        return [
            ...parent::rules(),
            ['currencyCode', function ($attribute) {
                if (!BillingCurrency::getByCode($this->currencyCode)) {
                    $this->addError($attribute, \Yii::t('steroids', 'Валюта не найдена'));
                }
            }],
            ['accountName', function ($attribute) {
                if (!$this->account) {
                    $this->addError($attribute, \Yii::t('steroids', 'Аккаунт не найден'));
                }
            }],
            ['methodName', function ($attribute) {
                if (!$this->method) {
                    $this->addError($attribute, \Yii::t('steroids', 'Метод оплаты не найден'));
                }
            }],
        ];
    }

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function execute()
    {
        if ($this->validate()) {
            // Amount
            $inAmount = $this->account->currency->amountToInt($this->inAmount);

            $transaction = \Yii::$app->db->beginTransaction();
            try {
                // Create order
                $this->order = $this->method
                    ->createOrder($this->account->userId, $this->account->currency->code, $inAmount, array_merge(
                        [
                            'description' => $this->description,
                            'redirectUrl' => $this->redirectUrl
                        ],
                        $this->custom
                    ));

                // Add charge/withdraw item
                if ($this->direction === PaymentDirection::CHARGE) {
                    $this->order->addOperation(
                        new PaymentChargeOperation([
                            'fromAccount' => $this->method->systemAccount,
                            'toAccount' => $this->account,
                            'document' => $this->order,
                        ])
                    );
                } else {
                    // Reserve amount
                    (new PaymentWithdrawReserveOperation([
                        'fromAccount' => $this->account,
                        'toAccount' => $this->method->systemAccount,
                        'document' => $this->order,
                    ]))->execute();

                    // Add rollback handler
                    $this->order->addFailureOperation(
                        new PaymentWithdrawRollbackOperation([
                            'fromAccount' => $this->method->systemAccount,
                            'toAccount' => $this->account,
                            'document' => $this->order,
                        ])
                    );
                }

                // Auto create request
                if (!$this->request) {
                    $this->request = RequestInfo::createFromYii();
                }

                // Start
                $this->process = $this->order->start(
                    $this->request,
                    $this->direction === PaymentDirection::WITHDRAW
                        ? PaymentMethodEnum::WITHDRAW
                        : PaymentMethodEnum::START
                );

                $transaction->commit();
            } catch (\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }
        }
    }

    /**
     * @return PaymentMethod|null
     */
    public function getMethod()
    {
        try {
            return PaymentMethod::getByName($this->methodName);
        } catch (PaymentException $e) {
        }
        return null;
    }

    /**
     * @return BillingAccount|null
     */
    public function getAccount()
    {
        if (!$this->_account) {
            $this->_account = BillingAccount::findOrCreate($this->accountName, $this->currencyCode, $this->user->getId());
        }
        return $this->_account;
    }

    /**
     * @inheritDoc
     */
    public function setAttributes($values, $safeOnly = true)
    {
        // Set method at first
        if (isset($values['methodName'])) {
            $this->methodName = $values['methodName'];
        }

        parent::setAttributes($values, $safeOnly);
    }

    /**
     * @inheritDoc
     */
    public function beforeValidate()
    {
        // Check custom required attributes
        if ($this->method) {
            $validator = new RequiredValidator();
            foreach ($this->method->params as $param) {
                if ($param->isRequired) {
                    $attribute = $param->name;
                    $value = $this->isAttributeSafe($attribute)
                        ? $this->$attribute
                        : ArrayHelper::getValue($this->custom, $attribute);
                    if ($validator->isEmpty($value)) {
                        $this->addError($attribute, $validator->message);
                    }
                }
            }
        }

        return parent::beforeValidate();
    }

    /**
     * @todo custom properties setting will fail if some validator is added for custom field
     * set custom fields manually, e.g. in static::setAttributes()
     *
     * @inheritDoc
     */
    public function onUnsafeAttribute($name, $value)
    {
        $customAttributes = $this->method ? ArrayHelper::getColumn($this->method->params, 'name') : [];
        if (in_array($name, $customAttributes)) {
            $this->custom[$name] = $value;
        } else {
            parent::onUnsafeAttribute($name, $value);
        }
    }
}

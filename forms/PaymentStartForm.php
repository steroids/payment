<?php

namespace steroids\payment\forms;

use steroids\auth\UserInterface;
use steroids\billing\models\BillingAccount;
use steroids\billing\models\BillingCurrency;
use steroids\core\structure\RequestInfo;
use steroids\payment\enums\PaymentDirection;
use steroids\payment\exceptions\PaymentException;
use steroids\payment\forms\meta\PaymentStartFormMeta;
use steroids\payment\models\PaymentMethod;
use steroids\payment\models\PaymentOrder;
use steroids\payment\operations\PaymentChargeOperation;
use steroids\payment\structure\PaymentProcess;
use yii\helpers\ArrayHelper;
use yii\validators\RequiredValidator;

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

    public UserInterface $user;

    public ?RequestInfo $request = null;

    public ?PaymentOrder $order = null;

    public ?PaymentProcess $process = null;

    public ?string $description = null;

    public ?string $redirectUrl = null;

    private ?BillingAccount $_account = null;

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
            // Create order
            $this->order = $this->method
                ->createOrder($this->account, $this->inAmount, array_merge(
                    $this->attributes,
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
                        'amount' => $this->inAmount,
                        'document' => $this->order,
                    ])
                );
            } else {
                // TODO Подумать над выводом...
                /*$this->order->addOperation(
                    new PaymentChargeOperation([
                        'fromAccount' => $this->account,
                        'toAccount' => $this->method->systemAccount,
                        'amount' => $this->inAmount,
                        'document' => $this->order,
                    ])
                );*/
            }

            // Auto create request
            if (!$this->request) {
                $this->request = RequestInfo::createFromYii();
            }

            // Start
            $this->process = $this->order->start($this->request);
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

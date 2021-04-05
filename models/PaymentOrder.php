<?php

namespace steroids\payment\models;

use steroids\auth\AuthModule;
use steroids\auth\UserInterface;
use steroids\billing\enums\BillingCurrencyRateDirectionEnum;
use steroids\billing\models\BillingCurrency;
use steroids\billing\operations\BaseBillingOperation;
use steroids\billing\operations\BaseOperation;
use steroids\core\base\Model;
use steroids\core\behaviors\UidBehavior;
use steroids\core\structure\RequestInfo;
use steroids\payment\enums\PaymentDirection;
use steroids\payment\enums\PaymentMethodEnum;
use steroids\payment\enums\PaymentStatus;
use steroids\payment\exceptions\PaymentException;
use steroids\payment\interfaces\ProviderWithdrawInterface;
use steroids\payment\models\meta\PaymentOrderMeta;
use steroids\payment\PaymentModule;
use steroids\payment\PaymentProcessEvent;
use steroids\payment\providers\BaseProvider;
use steroids\payment\structure\PaymentProcess;
use yii\base\InvalidConfigException;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\IdentityInterface;

/**
 * Class PaymentOrder
 * @package steroids\payment\models
 * @property-read array $providerParams
 * @property-read string $direction
 * @property-read array $methodParams
 * @property-read PaymentProviderLog $lastProviderLogger
 * @property-read IdentityInterface|UserInterface|Model $payerUser
 */
class PaymentOrder extends PaymentOrderMeta implements PaymentOrderInterface
{
    private ?array $_providerParams = null;

    /**
     * @inheritDoc
     */
    public static function instantiate($row)
    {
        return PaymentModule::instantiateClass(static::class, $row);
    }

    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return [
            ...parent::behaviors(),
            UidBehavior::class,
        ];
    }

    public function rules()
    {
        return [
            ...parent::rules(),
            [['!uid', '!providerParamsJson', '!externalId', '!outAmount', '!status'], 'safe'],
            ['status', 'default', 'value' => PaymentStatus::CREATED],
        ];
    }

    /**
     * @param BaseOperation $operation
     * @return $this
     */
    public function addFailureOperation(BaseOperation $operation)
    {
        return $this->addOperation($operation, PaymentStatus::FAILURE);
    }

    /**
     * @return string
     */
    public function getDirection()
    {
        return $this->method->direction === PaymentDirection::WITHDRAW
            ? BillingCurrencyRateDirectionEnum::SELL
            : BillingCurrencyRateDirectionEnum::BUY;
    }

    public function addOperation(BaseOperation $operation, $conditionStatus = PaymentStatus::SUCCESS)
    {
        $operation->payerUserId = $this->payerUserId;

        $json = $operation->toArray();
        foreach (['documentId', 'fromAccountId', 'toAccountId'] as $key) {
            ArrayHelper::remove($json, $key);
        }

        $items = $this->items;
        $item = new PaymentOrderItem([
            'orderId' => $this->primaryKey,
            'operationDump' => Json::encode($json),
            'position' => count($items),
            'documentId' => $operation->documentId,
            'conditionStatus' => $conditionStatus,
        ]);
        if ($operation instanceof BaseBillingOperation) {
            $item->fromAccountId = $operation->fromAccountId;
            $item->toAccountId = $operation->toAccountId;
        }

        $item->saveOrPanic();

        $items[] = $item;
        $this->populateRelation('items', $items);

        return $this;
    }

    /**
     * @param RequestInfo $request
     * @param string $callMethod
     * @return PaymentProcess
     * @throws InvalidConfigException
     * @throws PaymentException
     */
    public function start(RequestInfo $request, $callMethod = 'start')
    {
        if ($this->isWithdraw()) {
            $process = new PaymentProcess([
                'newStatus' => PaymentStatus::PROCESS,
                'responseText' => 'ok',
            ]);
        } else {
            $process = $this->callProvider($callMethod, $request);
        }

        $this->status = PaymentStatus::PROCESS;
        $this->saveOrPanic();

        return $process;
    }

    /**
     * @param RequestInfo $request
     * @return PaymentProcess
     * @throws InvalidConfigException
     * @throws PaymentException
     */
    public function callback(RequestInfo $request)
    {
        return $this->callProvider('callback', $request);
    }

    protected function callProvider(string $callMethod, RequestInfo $request)
    {
        // Start log
        $providerLog = new PaymentProviderLog([
            'orderId' => $this->primaryKey,
            'methodId' => $this->method->primaryKey,
            'providerName' => $this->method->providerName,
            'callMethod' => $callMethod,
            'startTime' => date('Y-m-d H:i:s'),
            'requestRaw' => $request->toRaw(),
        ]);
        $providerLog->saveOrPanic();

        // Populate logger to model
        $this->populateRelation('lastProviderLogger', $providerLog);

        // Get provider
        /** @var BaseProvider $provider */
        $provider = PaymentModule::getInstance()->getProvider($this->method->providerName);
        if (!$provider) {
            throw new InvalidConfigException("Not found payment provider '{$this->method->providerName}'");
        }

        if (
            !(PaymentModule::getInstance())->isManualWithdraw
            && $callMethod === PaymentDirection::WITHDRAW
            && !($provider instanceof ProviderWithdrawInterface)
        ) {
            throw new InvalidConfigException("Provider '{$this->method->providerName}' does not support withdrawal operations");
        }

        $eventsMap = [
            'start' => PaymentModule::EVENT_START,
            'callback' => PaymentModule::EVENT_CALLBACK,
            'withdraw' => PaymentModule::EVENT_WITHDRAW,
        ];

        // Run start
        $transaction = \Yii::$app->db->beginTransaction();
        try {
            // Call provider method
            /** @var PaymentProcess $process */
            $process = $provider->$callMethod($this, $request) ?: new PaymentProcess();
            $providerLog->responseRaw = $process->responseText;

            // Trigger event
            PaymentModule::getInstance()->trigger($eventsMap[$callMethod], new PaymentProcessEvent([
                'order' => $this,
                'request' => $request,
                'process' => $process,
            ]));

            // Save changes
            $this->saveOrPanic();

            $transaction->commit();
        } catch (\Exception $e) {
            // Store exception in log model
            $providerLog->errorRaw = (string)$e;

            $transaction->rollBack();
            throw $e;
        }

        // Check to finish
        if (!in_array($this->status, PaymentStatus::getFinishStatuses())
            && in_array($process->newStatus, PaymentStatus::getFinishStatuses())
        ) {
            $this->end($request, $process);
        }

        // End log
        $providerLog->endTime = date('Y-m-d H:i:s');
        $providerLog->saveOrPanic();

        return $process;
    }

    public function end(RequestInfo $request, PaymentProcess $process)
    {
        // Check is finished
        if (in_array($this->status, PaymentStatus::getFinishStatuses()) || $this->status === $process->newStatus) {
            return;
        }

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            //Confirm withdraw operation
            if (
                !(PaymentModule::getInstance())->isManualWithdraw
                && $this->isWithdraw()
                && $process->newStatus === PaymentStatus::SUCCESS
            ) {
                $process = $this->callProvider(PaymentMethodEnum::WITHDRAW, $request);
            }

            // Save new status
            $this->status = $process->newStatus;
            $this->saveOrPanic();

            // Execute items operations, if SUCCESS
            foreach ($this->items as $orderItem) {
                if ($this->status === $orderItem->conditionStatus) {
                    $orderItem->execute();
                }
            }

            // Trigger event
            PaymentModule::getInstance()->trigger(PaymentModule::EVENT_END, new PaymentProcessEvent([
                'order' => $this,
                'request' => $request,
                'process' => $process,
            ]));

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }


    /**
     * @return ActiveQuery
     */
    public function getLastProviderLogger()
    {
        return $this->hasOne(PaymentProviderLog::class, ['orderId' => 'id'])
            ->orderBy(['id' => SORT_DESC]);
    }

    /**
     * @return ActiveQuery
     */
    public function getPayerUser()
    {
        return $this->hasOne(AuthModule::getInstance()->userClass, ['id' => 'payerUserId']);
    }

    /**
     * @param string $message
     * @throws PaymentException
     */
    public function log(string $message)
    {
        if (!$this->lastProviderLogger) {
            throw new PaymentException('Cannot find provider logger for order:' . $this->primaryKey);
        }

        $this->lastProviderLogger->addLog($message);
    }

    public function getId()
    {
        return $this->primaryKey;
    }

    public function getPayerUserId()
    {
        return $this->payerUserId;
    }

    public function getOutAmount()
    {
        return $this->outAmount;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getMethodName()
    {
        return $this->method->name;
    }

    /**
     * @return array
     */
    public function getMethodParams()
    {
        return $this->methodParamsJson ? Json::decode($this->methodParamsJson) : [];
    }

    /**
     * @param string $key
     * @return mixed|null
     * @throws \Exception
     */
    public function getProviderParam(string $key)
    {
        return ArrayHelper::getValue($this->getProviderParams(), $key);
    }

    /**
     * @return array
     */
    public function getProviderParams()
    {
        if (!$this->_providerParams) {
            $this->_providerParams = $this->providerParamsJson ? Json::decode($this->providerParamsJson) : [];
        }
        return $this->_providerParams;
    }

    /**
     * @param string $key
     * @param $value
     */
    public function setProviderParam(string $key, $value)
    {
        $this->_providerParams = array_merge($this->getProviderParams(), [
            $key => $value,
        ]);
        $this->providerParamsJson = !empty($this->_providerParams) ? Json::encode($this->_providerParams) : null;
    }

    public function setExternalId(string $value)
    {
        $this->externalId = $value;
    }

    public function setExternalAmount(int $amount)
    {
        $this->realOutAmount = $amount;

        $rateDirection = $this->method->direction === 'withdraw'
            ? BillingCurrencyRateDirectionEnum::SELL
            : BillingCurrencyRateDirectionEnum::BUY;

        $this->realInAmount = $this->realOutAmount === $this->outAmount
            ? $this->inAmount
            : BillingCurrency::convert($this->outCurrencyCode, $this->inCurrencyCode, $this->realOutAmount, $rateDirection);
    }

    public function setErrorMessage(string $value)
    {
        $this->errorMessage = $value;
    }

    public function isCharge()
    {
        return $this->method->direction === PaymentDirection::CHARGE;
    }

    public function isWithdraw()
    {
        return $this->method->direction === PaymentDirection::WITHDRAW;
    }

    /**
     * @inheritDoc
     */
    public function getItems()
    {
        return parent::getItems()->orderBy(['position' => SORT_ASC]);
    }

    /**
     * @param UserInterface|Model|IdentityInterface $user
     * @return array|bool
     */
    public function canUpdate($user)
    {
        return ($user->getId() === $this->payerUserId && $this->canUpdated()) || parent::canUpdate($user);
    }

    /**
     * @inheritDoc
     */
    public function beforeSave($insert)
    {
        $this->calculateOutAmount();
        return parent::beforeSave($insert);
    }

    public function calculateOutAmount()
    {
        if (!$this->inAmount) {
            return;
        }

        $billingCurrency = BillingCurrency::getByCode($this->inCurrencyCode);
        $rateDirection = $this->method->direction === PaymentDirection::WITHDRAW
            ? BillingCurrencyRateDirectionEnum::SELL
            : BillingCurrencyRateDirectionEnum::BUY;

        $outAmount = $billingCurrency->to($this->method->outCurrencyCode, $this->inAmount, $rateDirection);
        $outAmount = $this->method->direction === PaymentDirection::WITHDRAW
            ? $outAmount - ($outAmount * ($this->outCommissionPercent / 100))
            : $outAmount + ($outAmount * ($this->outCommissionPercent / 100));

        if ($this->method->outCommissionCurrencyCode) {
            $amountCommissionFixed = $billingCurrency::convert(
                $this->method->outCommissionCurrencyCode,
                $this->method->outCurrencyCode,
                $this->method->outCommissionFixed,
                $rateDirection
            );

            $outAmount = $this->method->direction === PaymentDirection::WITHDRAW
                ? $outAmount - $amountCommissionFixed
                : $outAmount + $amountCommissionFixed;
        }

        $this->outAmount = ceil($outAmount);
        $this->rateUsd = $billingCurrency->rateByDirection($rateDirection);
    }
}
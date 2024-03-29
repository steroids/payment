<?php

namespace steroids\payment\models;

interface PaymentOrderInterface
{
    /**
     * @return int
     */
    public function getId();

    /**
     * @return int
     */
    public function getPayerUserId();

    /**
     * @param string $key
     * @return mixed|null
     */
    public function getProviderParam(string $key);

    /**
     * @return array
     */
    public function getProviderParams();

    /**
     * @return string
     */
    public function getInCurrencyCode();

    /**
     * @return string
     */
    public function getOutCurrencyCode();

    /**
     * @return int
     */
    public function getOutAmount();

    /**
     * @return string
     */
    public function getDescription();

    /**
     * @return string
     */
    public function getMethodName();

    /**
     * @return mixed
     */
    public function isCharge();
    
    /**
     * @return mixed
     */
    public function isWithdraw();

    /**
     * @param string $key
     * @param $value
     */
    public function setProviderParam(string $key, $value);

    /**
     * @param string $value
     */
    public function setExternalId(string $value);

    /**
     * @param int $amount
     */
    public function setExternalAmount(int $amount);

    /**
     * @param string $value
     */
    public function setErrorMessage(string $value);

    /**
     * @param string $message
     */
    public function log(string $message);

}

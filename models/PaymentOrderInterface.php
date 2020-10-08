<?php

namespace steroids\payment\models;

interface PaymentOrderInterface
{
    /**
     * @return int
     */
    public function getId();

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
     * @param string $key
     * @param $value
     */
    public function setProviderParam(string $key, $value);

    /**
     * @param string $value
     */
    public function setExternalId(string $value);

    /**
     * @param string $message
     */
    public function log(string $message);

}

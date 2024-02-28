<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class RegisterNewAccount
{
    private $accNo    = null;
    private $accName  = "";
    private $remunFee = null;
    private $broker;

    public function __construct(
        AccountNumber $accNo,
        $accName,
        $broker,
        $remunFee = 0
    ) {
        $this->accNo    = $accNo;
        $this->accName  = $accName;
        $this->broker  = $broker;
        $this->remunFee = intval($remunFee);
    }

    public function getAccountNumber()
    {
        return $this->accNo;
    }

    public function getAccountName()
    {
        return $this->accName;
    }

    public function getRemunerationFee()
    {
        return $this->remunFee;
    }

    public function getBroker()
    {
        return $this->broker;
    }
}

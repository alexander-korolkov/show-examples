<?php

namespace Fxtm\CopyTrading\Application\Leader;

use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class ChangeLeverage
{
    private $accNo    = null;
    private $leverage = null;
    private $broker;

    public function __construct(AccountNumber $accNo, $leverage, $broker)
    {
        $this->accNo    = $accNo;
        $this->broker    = $broker;
        $this->leverage = intval($leverage);
    }

    public function getAccountNumber()
    {
        return $this->accNo;
    }

    public function getLeverage()
    {
        return $this->leverage;
    }

    public function getBroker()
    {
        return $this->broker;
    }
}

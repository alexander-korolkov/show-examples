<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class ChangeCopyCoefficient
{
    private $accNo = null;
    private $copyCoef = 1.00;
    private $broker;

    public function __construct(AccountNumber $accNo, $copyCoef, $broker)
    {
        $this->accNo = $accNo;
        $this->copyCoef = $copyCoef;
        $this->broker = $broker;
    }

    public function getAccountNumber()
    {
        return $this->accNo;
    }

    public function getCopyCoefficient()
    {
        return $this->copyCoef;
    }

    public function getBroker()
    {
        return $this->broker;
    }
}

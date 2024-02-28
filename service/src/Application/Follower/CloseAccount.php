<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class CloseAccount
{
    private $accNo = null;
    private $broker;

    public function __construct(AccountNumber $accNo, $broker)
    {
        $this->accNo = $accNo;
        $this->broker = $broker;
    }

    public function getAccountNumber()
    {
        return $this->accNo;
    }

    public function getBroker()
    {
        return $this->broker;
    }
}

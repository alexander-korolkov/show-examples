<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class PauseCopying
{
    private $accNo = null;

    public function __construct(AccountNumber $accNo)
    {
        $this->accNo = $accNo;
    }

    public function getAccountNumber()
    {
        return $this->accNo;
    }
}

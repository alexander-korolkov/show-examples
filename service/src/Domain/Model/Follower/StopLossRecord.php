<?php

namespace Fxtm\CopyTrading\Domain\Model\Follower;

use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;

class StopLossRecord
{
    private $accNo     = null;
    private $level     = 0;
    private $equity    = null;
    private $action    = 0;
    private $changedAt = null;

    public function __construct(
        AccountNumber $accNo,
        $level,
        Money $equity,
        $action,
        DateTime $changedAt
    ) {
        $this->accNo     = $accNo;
        $this->level     = $level;
        $this->equity    = $equity;
        $this->action    = $action;
        $this->changedAt = $changedAt;
    }

    public function getAccountNumber()
    {
        return $this->accNo;
    }

    public function getLevel()
    {
        return $this->level;
    }

    public function getEquity()
    {
        return $this->equity;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function getChangedAt()
    {
        return $this->changedAt;
    }
}

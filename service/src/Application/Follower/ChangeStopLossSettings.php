<?php

namespace Fxtm\CopyTrading\Application\Follower;

use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

class ChangeStopLossSettings
{
    private $accNo = null;
    private $stopLossLevel = 0;
    private $stopCopyingOnStopLoss = true;

    public function __construct(
        AccountNumber $accNo,
        $stopLossLevel,
        $stopCopyingOnStopLoss = true
    ) {
        $this->accNo = $accNo;
        $this->stopLossLevel = intval($stopLossLevel);
        $this->stopCopyingOnStopLoss = boolval($stopCopyingOnStopLoss);
    }

    public function getAccountNumber()
    {
        return $this->accNo;
    }

    public function getStopLossLevel()
    {
        return $this->stopLossLevel;
    }

    public function getStopCopyingOnStopLoss()
    {
        return $this->stopCopyingOnStopLoss;
    }
}

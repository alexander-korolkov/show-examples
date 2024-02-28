<?php

namespace Fxtm\CopyTrading\Domain\Model\Follower;

use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;

interface StopLossHistory
{
    /**
     *
     * @param AccountNumber $accNo
     * @return StopLossRecord
     */
    public function getLastStopLossRecord(AccountNumber $accNo);
}

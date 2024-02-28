<?php

namespace Fxtm\CopyTrading\Application;

use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\ServerAwareAccount;

interface StatisticsService
{
    public function importEquityStatistics(ServerAwareAccount $acc);
    public function getFirstDepositDatetime(AccountNumber $accNo);
    public function getLeaderEquityStatistics(AccountNumber $accNo);
}

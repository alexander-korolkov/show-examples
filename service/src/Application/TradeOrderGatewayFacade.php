<?php

namespace Fxtm\CopyTrading\Application;

use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Shared\ServerAwareAccount;

interface TradeOrderGatewayFacade
{
    /**
     *
     * @param int $server
     * @return TradeOrderGateway
     */
    public function getForServer(int $server): TradeOrderGateway;

    /**
     *
     * @param ServerAwareAccount $acc
     * @return TradeOrderGateway
     */
    public function getForAccount(ServerAwareAccount $acc): TradeOrderGateway;

    public function getOrdersForPeriod(ServerAwareAccount $acc, DateTime $start, DateTime $end);
    public function getOrderHistory(ServerAwareAccount $acc);
    public function hasOpenPositions(ServerAwareAccount $acc);
    public function getApplicableSessions(ServerAwareAccount $acc);
    public function getApplicableHolidays(ServerAwareAccount $acc);

    /**
     * Checks if the given logins had any trading activity since given date
     * Returns array of logins without trade activity
     *
     * @param array $logins
     * @param DateTime $date
     * @return array
     */
    public function getLoginsWithTradingSince(array $logins, DateTime $date) : array;
}

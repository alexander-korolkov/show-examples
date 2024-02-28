<?php

namespace Fxtm\CopyTrading\Application;

use Fxtm\CopyTrading\Domain\Common\DateTime;
use Fxtm\CopyTrading\Domain\Model\Shared\AccountNumber;
use Fxtm\CopyTrading\Domain\Model\Shared\Money;

interface TradeOrderGateway
{
    public const ACC_NO_PARAM = 'acc_no';
    public const SERVER_ID_PARAM = 'server_id';

    public function getOrdersForPeriod(AccountNumber $accNo, DateTime $start, DateTime $end);
    public function getOrderHistory(AccountNumber $accNo);
    public function hasOpenPositions(AccountNumber $accNo);
    public function countOpenPositions(AccountNumber $accNo);
    public function getApplicableSessions(AccountNumber $accNo);
    public function getApplicableHolidays(AccountNumber $accNo);

    /**
     * Returns the logins of the accounts without trading since given $date
     * from concrete server limited by $limit
     *
     * @param array $logins
     * @param DateTime $date
     * @return array
     */
    public function getLoginsWithTradingSince(array $logins, DateTime $date) : array;

    /**
     * Returns array with logins and counts of opened/closed/continued orders in last few days
     *
     * @param string $days
     * @param array $logins
     * @param string $days
     * @return array
     */
    public function getOrdersCountForLastDays(string $days, array $logins = []) : array;

    /**
     * Returns array with given logins and counts of closed trade positions
     * for each login
     *
     * @param array $logins
     * @return array
     */
    public function getClosedOrdersCountForAccounts(array $logins) : array;

    /**
     * Returns array of logins that have orders in the time interval, but didn't trade after this interval
     *
     * @param string $days
     * @return array
     */
    public function getLoginsWithoutTradingInLastDays(string $days): array;

    /**
     * Returns account equity
     *
     * @param AccountNumber $accNo
     * @return float
     */
    public function getAccountEquity(AccountNumber $accNo) : float;

    /**
     * Returns array [ login => current equity ]
     *
     * @param array $accountNumbers
     * @param DateTime $onDatetime on what datetime equities are needed
     * @return array
     */
    public function getBulkAccountEquities(array $accountNumbers, DateTime $onDatetime) : array;
}
